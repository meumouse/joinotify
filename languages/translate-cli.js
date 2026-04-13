import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import gettextParser from "gettext-parser";
import { Translate } from "@google-cloud/translate/build/src/v2/index.js";
import dotenv from "dotenv";

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const TEXT_DOMAIN = "joinotify";
const POT_FILE = path.join(__dirname, `${TEXT_DOMAIN}.pot`);
const BATCH_SIZE = 50;
const DELAY_BETWEEN_BATCHES = 1000;
const MAX_RETRIES = 3;

const LANGUAGES = {
//  de_DE: { code: "de", name: "German (Germany)" },
//  es_ES: { code: "es", name: "Spanish (Spain)" },
//  fr_FR: { code: "fr", name: "French (France)" },
//  it_IT: { code: "it", name: "Italian (Italy)" },
//  nl_NL: { code: "nl", name: "Dutch (Netherlands)" },
  pt_BR: { code: "pt", name: "Portuguese (Brazil)" },
//  pt_PT: { code: "pt-PT", name: "Portuguese (Portugal)" },
//  zh_CN: { code: "zh-CN", name: "Chinese (Simplified)" },
};

const translate = new Translate({
  key: process.env.GOOGLE_TRANSLATE_API_KEY,
});

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
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

function extractMsgIds(poData) {
  const translations = poData.translations[""] || {};
  const msgIds = new Map();

  for (const [msgid, entry] of Object.entries(translations)) {
    if (msgid === "") {
      continue;
    }

    msgIds.set(msgid, entry);
  }

  return msgIds;
}

function findStringsToTranslate(potMsgIds, existingPoData) {
  const toTranslate = [];
  const existingTranslations = existingPoData?.translations?.[""] || {};

  for (const [msgid, potEntry] of potMsgIds) {
    const existing = existingTranslations[msgid];

    if (!existing || !existing.msgstr || existing.msgstr[0] === "") {
      toTranslate.push({
        msgid,
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

async function translateStrings(strings, targetLangCode) {
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

      for (let j = 0; j < stringsToTranslate.length; j++) {
        translations[stringsToTranslate[j]] = translatedArray[j];
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
  const headers = poData.translations[""][""];

  headers.msgstr[0] = headers.msgstr[0]
    .replace("LANGUAGE <LL@li.org>", `${LANGUAGES[langCode].name}`)
    .replace("Language: \\n", `Language: ${langCode.replace("_", "-")}\\n`)
    .replace(
      "PO-Revision-Date: ",
      `PO-Revision-Date: ${new Date().toISOString()}\\n`
    );

  const existingTranslations = existingPoData?.translations?.[""] || {};

  for (const msgid of Object.keys(poData.translations[""])) {
    if (msgid === "") {
      continue;
    }

    if (newTranslations[msgid]) {
      poData.translations[""][msgid].msgstr = [newTranslations[msgid]];
    } else if (
      existingTranslations[msgid] &&
      existingTranslations[msgid].msgstr &&
      existingTranslations[msgid].msgstr[0]
    ) {
      poData.translations[""][msgid].msgstr = existingTranslations[msgid].msgstr;
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

function generateJsonFile(poData, jsonPath, langCode) {
  const wpFormat = {
    domain: TEXT_DOMAIN,
    locale_data: {
      [TEXT_DOMAIN]: {
        "": {
          domain: TEXT_DOMAIN,
          lang: langCode,
          "plural-forms": "nplurals=2; plural=(n != 1);",
        },
      },
    },
  };

  for (const [msgid, entry] of Object.entries(poData.translations[""])) {
    if (msgid === "") {
      continue;
    }

    if (entry.msgstr && entry.msgstr[0]) {
      wpFormat.locale_data[TEXT_DOMAIN][msgid] = entry.msgstr;
    }
  }

  fs.writeFileSync(jsonPath, JSON.stringify(wpFormat, null, 2));
}

function writeTranslationArtifacts(poData, poPath, moPath, jsonPath, langCode) {
  writePoFile(poData, poPath);
  console.log(`   Written: ${path.basename(poPath)}`);

  writeMoFile(poData, moPath);
  console.log(`   Written: ${path.basename(moPath)}`);

  generateJsonFile(poData, jsonPath, langCode);
  console.log(`   Written: ${path.basename(jsonPath)}`);
}

async function main() {
  console.log("flexifyDashboard Translation Script (Google Cloud Translation)");
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
    const jsonPath = path.join(
      __dirname,
      `${TEXT_DOMAIN}-${langCode}-${TEXT_DOMAIN}.json`
    );

    let existingPoData = null;

    if (fs.existsSync(poPath)) {
      try {
        existingPoData = parsePoFile(poPath);
        console.log("   Loaded existing translations");
      } catch (error) {
        console.warn(`   Warning: Could not parse existing PO file: ${error.message}`);
      }
    }

    const stringsToTranslate = findStringsToTranslate(potMsgIds, existingPoData);
    let poData;

    if (stringsToTranslate.length === 0) {
      console.log("   All strings already translated");
      poData = createPoFile(potData, existingPoData, {}, langCode);
    } else {
      if (!process.env.GOOGLE_TRANSLATE_API_KEY) {
        console.error("Error: GOOGLE_TRANSLATE_API_KEY environment variable is not set.");
        console.error("Create a .env file with your API key or set it directly:");
        console.error("  GOOGLE_TRANSLATE_API_KEY=xxx node translate-cli.js");
        console.error("  GOOGLE_TRANSLATE_API_KEY=xxx node translate-cli.js --lang=pt_BR");
        console.error("\nGet an API key from: https://console.cloud.google.com/apis/credentials");
        process.exit(1);
      }

      console.log(`   Found ${stringsToTranslate.length} strings to translate`);

      const newTranslations = await translateStrings(stringsToTranslate, langInfo.code);
      console.log(`   Received ${Object.keys(newTranslations).length} translations`);

      poData = createPoFile(potData, existingPoData, newTranslations, langCode);
    }

    writeTranslationArtifacts(poData, poPath, moPath, jsonPath, langCode);
  }

  console.log("\nTranslation complete.");
}

main().catch((error) => {
  console.error("Fatal error:", error);
  process.exit(1);
});
