import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import gettextParser from "gettext-parser";
import { Translate } from "@google-cloud/translate/build/src/v2/index.js";
import dotenv from "dotenv";
import { translateStringsOpenAI } from "./openai-translate.js";
import { poDataToPhp } from "./l10n-php.js";
import { writeScriptTranslations } from "./script-translations.js";

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const TEXT_DOMAIN = "joinotify";
const PLUGIN_ROOT = path.resolve(__dirname, "..");
const POT_FILE = path.join(__dirname, `${TEXT_DOMAIN}.pot`);
const BATCH_SIZE = 50;
const DELAY_BETWEEN_BATCHES = 1000;
const MAX_RETRIES = 3;

// `plural` is the locale's Plural-Forms rule, as WordPress core ships it.
const LANGUAGES = {
  en_US: { code: "en", name: "English (United States)", plural: "nplurals=2; plural=(n != 1);" },
  es_ES: { code: "es", name: "Spanish (Spain)", plural: "nplurals=2; plural=(n != 1);" },
  pt_BR: { code: "pt", name: "Portuguese (Brazil)", plural: "nplurals=2; plural=(n > 1);" },
  pt_PT: { code: "pt-PT", name: "Portuguese (Portugal)", plural: "nplurals=2; plural=(n != 1);" },
  de_DE: { code: "de", name: "German (Germany)", plural: "nplurals=2; plural=(n != 1);" },
  fr_FR: { code: "fr", name: "French (France)", plural: "nplurals=2; plural=(n > 1);" },
  it_IT: { code: "it", name: "Italian (Italy)", plural: "nplurals=2; plural=(n != 1);" },
//  nl_NL: { code: "nl", name: "Dutch (Netherlands)", plural: "nplurals=2; plural=(n != 1);" },
//  zh_CN: { code: "zh-CN", name: "Chinese (Simplified)", plural: "nplurals=1; plural=0;" },
};

// gettext's separator between a message context and its msgid.
const CONTEXT_GLUE = "\u0004";

// Suffix of the key under which an entry's plural form is translated.
const PLURAL_SUFFIX = "\u0000plural";

const ENGINES = new Set(["google", "openai"]);
const DEFAULT_ENGINE = "google";

const translate = new Translate({
  key: process.env.GOOGLE_TRANSLATE_API_KEY,
});

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function getSelectedEngine() {
  const args = process.argv.slice(2);
  const flagIndex = args.findIndex((arg) => arg === "--engine" || arg === "-e");
  const inlineArg = args.find((arg) => arg.startsWith("--engine="));

  let engine = process.env.TRANSLATE_ENGINE || DEFAULT_ENGINE;

  if (flagIndex !== -1 && args[flagIndex + 1]) {
    engine = args[flagIndex + 1];
  } else if (inlineArg) {
    engine = inlineArg.split("=")[1];
  }

  engine = engine.toLowerCase();

  if (!ENGINES.has(engine)) {
    console.error(`Error: Unsupported engine "${engine}".`);
    console.error(`Available engines: ${Array.from(ENGINES).join(", ")}`);
    process.exit(1);
  }

  return engine;
}

function getRetranslateIdentical() {
  const args = process.argv.slice(2);

  return (
    args.includes("--retranslate-identical") ||
    args.includes("-r") ||
    process.env.RETRANSLATE_IDENTICAL === "1"
  );
}

function getSelectedLanguages() {
  const args = process.argv.slice(2);
  const langFlagIndex = args.findIndex((arg) => arg === "--lang" || arg === "-l");
  const inlineLangArg = args.find((arg) => arg.startsWith("--lang="));

  let selectedLangCode = null;

  if (langFlagIndex !== -1) {
    selectedLangCode = args[langFlagIndex + 1] || null;
  } else if (inlineLangArg) {
    selectedLangCode = inlineLangArg.split("=")[1] || null;
  } else if (args[0] && !args[0].startsWith("-")) {
    selectedLangCode = args[0];
  }

  if (!selectedLangCode) {
    return LANGUAGES;
  }

  if (!LANGUAGES[selectedLangCode]) {
    console.error(`Error: Unsupported language "${selectedLangCode}".`);
    console.error(`Available languages: ${Object.keys(LANGUAGES).join(", ")}`);
    process.exit(1);
  }

  return {
    [selectedLangCode]: LANGUAGES[selectedLangCode],
  };
}

function parsePoFile(filePath) {
  const content = fs.readFileSync(filePath);
  return gettextParser.po.parse(content);
}

function getPluralCount(langCode) {
  const match = /nplurals\s*=\s*(\d+)/.exec(LANGUAGES[langCode].plural);

  return match ? Number(match[1]) : 2;
}

function toEntryKey(msgctxt, msgid) {
  return msgctxt ? `${msgctxt}${CONTEXT_GLUE}${msgid}` : msgid;
}

/**
 * Index every entry of the template, in every context, by "context\4msgid".
 *
 * Only the default context was read before, so strings passed through `_x()`
 * never reached the catalogues.
 */
function extractMsgIds(poData) {
  const msgIds = new Map();

  for (const [msgctxt, entries] of Object.entries(poData.translations || {})) {
    for (const [msgid, entry] of Object.entries(entries)) {
      if (msgid === "") {
        continue;
      }

      msgIds.set(toEntryKey(msgctxt, msgid), entry);
    }
  }

  return msgIds;
}

/**
 * Queue what is still untranslated.
 *
 * Each item carries the text to translate, the key its translation is stored
 * under and, when the bare text is ambiguous, a context for the engine. A
 * plural entry queues its singular and its plural form separately, so a
 * catalogue that only has the singular gets the plural without re-translating
 * the rest.
 */
function findStringsToTranslate(
  potMsgIds,
  existingPoData,
  { retranslateIdentical = false, isEnglishTarget = false, pluralCount = 2 } = {}
) {
  const toTranslate = [];

  // For non-English targets, a translation equal to the source string is
  // almost always an untranslated passthrough left by a previous run. The
  // default incremental check treats a non-empty msgstr as "done" and would
  // skip it forever, so opt in to re-queueing those entries. (Legitimately
  // identical strings — brand/country names — are re-sent but returned
  // unchanged by the engine, so this only costs a few extra tokens.)
  const isPassthrough = (translation, source) =>
    retranslateIdentical && !isEnglishTarget && translation === source;

  for (const [key, potEntry] of potMsgIds) {
    const existing = existingPoData?.translations?.[potEntry.msgctxt || ""]?.[potEntry.msgid];
    const msgstr = existing?.msgstr || [];

    if (!msgstr[0] || isPassthrough(msgstr[0], potEntry.msgid)) {
      toTranslate.push({
        key,
        msgid: potEntry.msgid,
        context: potEntry.msgctxt || "",
        comments: potEntry.comments,
      });
    }

    if (!potEntry.msgid_plural) {
      continue;
    }

    const pluralForms = Array.from({ length: pluralCount - 1 }, (_, index) => msgstr[index + 1]);

    if (pluralForms.some((form) => !form) || isPassthrough(msgstr[1], potEntry.msgid_plural)) {
      toTranslate.push({
        key: key + PLURAL_SUFFIX,
        msgid: potEntry.msgid_plural,
        context: [`plural form of "${potEntry.msgid}"`, potEntry.msgctxt].filter(Boolean).join("; "),
        comments: potEntry.comments,
      });
    }
  }

  return toTranslate;
}

async function translateBatchWithRetry(stringsToTranslate, targetLangCode, retryCount = 0) {
  try {
    const [results] = await translate.translate(stringsToTranslate, {
      from: "en",
      to: targetLangCode,
      format: "text",
    });

    return Array.isArray(results) ? results : [results];
  } catch (error) {
    if (retryCount < MAX_RETRIES && error.message.includes("Rate Limit")) {
      const delay = Math.pow(2, retryCount + 1) * 1000;
      console.log(
        `    Rate limited. Waiting ${delay / 1000}s before retry ${retryCount + 1}/${MAX_RETRIES}...`
      );
      await sleep(delay);

      return translateBatchWithRetry(stringsToTranslate, targetLangCode, retryCount + 1);
    }

    throw error;
  }
}

async function translateStrings(strings, langInfo, engine) {
  if (strings.length === 0) {
    return {};
  }

  if (engine === "openai") {
    return translateStringsOpenAI(strings, langInfo);
  }

  return translateStringsGoogle(strings, langInfo.code);
}

async function translateStringsGoogle(strings, targetLangCode) {
  if (strings.length === 0) {
    return {};
  }

  const translations = {};

  for (let i = 0; i < strings.length; i += BATCH_SIZE) {
    const batch = strings.slice(i, i + BATCH_SIZE);
    const batchNum = Math.floor(i / BATCH_SIZE) + 1;
    const totalBatches = Math.ceil(strings.length / BATCH_SIZE);

    console.log(
      `    Translating batch ${batchNum}/${totalBatches} (${batch.length} strings)...`
    );

    const stringsToTranslate = batch.map((item) => item.msgid);

    try {
      const translatedArray = await translateBatchWithRetry(stringsToTranslate, targetLangCode);

      for (let j = 0; j < batch.length; j++) {
        translations[batch[j].key] = translatedArray[j];
      }
    } catch (error) {
      console.error(`    Error translating batch: ${error.message}`);
    }

    if (i + BATCH_SIZE < strings.length) {
      await sleep(DELAY_BETWEEN_BATCHES);
    }
  }

  return translations;
}

function createPoFile(potData, existingPoData, newTranslations, langCode) {
  const poData = JSON.parse(JSON.stringify(potData));
  const pluralCount = getPluralCount(langCode);

  // gettext-parser writes the header entry from `headers`, so that is where the
  // locale's language and plural rule go.
  poData.headers = {
    ...poData.headers,
    Language: langCode,
    "Plural-Forms": LANGUAGES[langCode].plural,
  };

  for (const [msgctxt, entries] of Object.entries(poData.translations)) {
    for (const [msgid, entry] of Object.entries(entries)) {
      if (msgid === "") {
        continue;
      }

      const key = toEntryKey(msgctxt, msgid);
      const existing = existingPoData?.translations?.[msgctxt]?.[msgid]?.msgstr || [];
      const singular = newTranslations[key] || existing[0] || "";

      if (!entry.msgid_plural) {
        entry.msgstr = [singular];
        continue;
      }

      entry.msgstr = [singular];

      for (let index = 1; index < pluralCount; index++) {
        entry.msgstr.push(newTranslations[key + PLURAL_SUFFIX] || existing[index] || "");
      }
    }
  }

  return poData;
}

function writePoFile(poData, outputPath) {
  fs.writeFileSync(outputPath, gettextParser.po.compile(poData));
}

function writeMoFile(poData, outputPath) {
  fs.writeFileSync(outputPath, gettextParser.mo.compile(poData));
}

function writePhpFile(poData, outputPath) {
  fs.writeFileSync(outputPath, poDataToPhp(poData));
}

function writeTranslationArtifacts(poData, poPath, moPath, phpPath, langCode) {
  writePoFile(poData, poPath);
  console.log(`   Written: ${path.basename(poPath)}`);

  writeMoFile(poData, moPath);
  console.log(`   Written: ${path.basename(moPath)}`);

  writePhpFile(poData, phpPath);
  console.log(`   Written: ${path.basename(phpPath)}`);

  writeScriptTranslations(poData, {
    pluginRoot: PLUGIN_ROOT,
    outputDir: __dirname,
    textDomain: TEXT_DOMAIN,
    locale: langCode,
  });
}

async function main() {
  const engine = getSelectedEngine();
  const retranslateIdentical = getRetranslateIdentical();
  const engineLabel = engine === "openai" ? "OpenAI (AI)" : "Google Cloud Translation";

  console.log(`Joinotify Translation Script (${engineLabel})`);
  if (retranslateIdentical) {
    console.log("Mode: re-translating entries whose translation equals the source string");
  }
  console.log("===========================================================\n");

  const selectedLanguages = getSelectedLanguages();

  if (!fs.existsSync(POT_FILE)) {
    console.error(`Error: POT file not found: ${POT_FILE}`);
    process.exit(1);
  }

  console.log("Parsing POT file...");
  const potData = parsePoFile(POT_FILE);
  const potMsgIds = extractMsgIds(potData);
  console.log(`   Found ${potMsgIds.size} translatable strings\n`);

  for (const [langCode, langInfo] of Object.entries(selectedLanguages)) {
    console.log(`\nProcessing ${langInfo.name} (${langCode})...`);

    const poPath = path.join(__dirname, `${TEXT_DOMAIN}-${langCode}.po`);
    const moPath = path.join(__dirname, `${TEXT_DOMAIN}-${langCode}.mo`);
    const phpPath = path.join(__dirname, `${TEXT_DOMAIN}-${langCode}.l10n.php`);

    let existingPoData = null;

    if (fs.existsSync(poPath)) {
      try {
        existingPoData = parsePoFile(poPath);
        console.log("   Loaded existing translations");
      } catch (error) {
        console.warn(`   Warning: Could not parse existing PO file: ${error.message}`);
      }
    }

    const stringsToTranslate = findStringsToTranslate(potMsgIds, existingPoData, {
      retranslateIdentical,
      isEnglishTarget: langInfo.code === "en",
      pluralCount: getPluralCount(langCode),
    });
    let poData;

    if (stringsToTranslate.length === 0) {
      console.log("   All strings already translated");
      poData = createPoFile(potData, existingPoData, {}, langCode);
    } else {
      if (engine === "google" && !process.env.GOOGLE_TRANSLATE_API_KEY) {
        console.error("Error: GOOGLE_TRANSLATE_API_KEY environment variable is not set.");
        console.error("Create a .env file with your API key or set it directly:");
        console.error("  GOOGLE_TRANSLATE_API_KEY=xxx node translate-cli.js");
        console.error("  GOOGLE_TRANSLATE_API_KEY=xxx node translate-cli.js --lang=pt_BR");
        console.error("\nGet an API key from: https://console.cloud.google.com/apis/credentials");
        process.exit(1);
      }

      if (engine === "openai" && !process.env.OPENAI_API_KEY) {
        console.error("Error: OPENAI_API_KEY environment variable is not set.");
        console.error("Create a .env file with your API key or set it directly:");
        console.error("  OPENAI_API_KEY=sk-xxx node translate-cli.js --engine=openai");
        console.error("  OPENAI_API_KEY=sk-xxx node translate-cli.js --engine=openai --lang=pt_BR");
        console.error("\nOptional: OPENAI_MODEL (default gpt-4o-mini), OPENAI_BASE_URL.");
        console.error("Get an API key from: https://platform.openai.com/api-keys");
        process.exit(1);
      }

      console.log(`   Found ${stringsToTranslate.length} strings to translate`);

      const newTranslations = await translateStrings(stringsToTranslate, langInfo, engine);
      console.log(`   Received ${Object.keys(newTranslations).length} translations`);

      poData = createPoFile(potData, existingPoData, newTranslations, langCode);
    }

    writeTranslationArtifacts(poData, poPath, moPath, phpPath, langCode);
  }

  console.log("\nTranslation complete.");
}

main().catch((error) => {
  console.error("Fatal error:", error);
  process.exit(1);
});
