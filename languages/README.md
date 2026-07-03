# Joinotify — Pipeline de Internacionalização (i18n)

Este diretório contém um pipeline **autossuficiente em Node.js** (ESM) para gerar, traduzir
e compilar os arquivos de tradução do plugin Joinotify. O *text domain* do plugin é
`joinotify`, e todos os artefatos são gerados aqui dentro de `languages/`.

O fluxo completo é:

```
código-fonte (.php/.js/.ts/.vue)
        │  npm run pot
        ▼
   joinotify.pot  ───────────────┐
        │  npm run translate(:ai) │ (preenche os .po)
        ▼                         │
joinotify-<locale>.po             │
        │  npm run compile:mo     │
        │  npm run compile:php    │
        ▼                         ▼
.mo   .l10n.php   *.json (por handle de script JS)
```

---

## Requisitos

- **Node.js 18+** (usa `fetch` global e ESM nativo).
- Para tradução automática, uma chave de API:
  - **Google Cloud Translation** (motor padrão), ou
  - **OpenAI** (motor de IA, recomendado — veja o porquê em [Qual motor usar](#qual-motor-usar)).

## Instalação

```bash
cd languages
npm install
```

As dependências são `gettext-parser` (leitura/escrita de `.po`/`.mo`/`.pot`),
`@google-cloud/translate` e `dotenv`.

## Configuração das chaves de API

Copie o arquivo de exemplo e preencha as chaves. O `.env` é **ignorado pelo Git**.

```bash
cp .env.example .env
```

```ini
# Chave da API Google Cloud Translation (motor: google, padrão)
GOOGLE_TRANSLATE_API_KEY=AIza...

# Chave da API OpenAI (motor: openai, tradução com IA)
OPENAI_API_KEY=sk-...
# Sobrescritas opcionais do motor OpenAI
OPENAI_MODEL=gpt-4o-mini
# OPENAI_BASE_URL=https://api.openai.com/v1

# Opcional: motor padrão quando --engine não é informado (google | openai)
# TRANSLATE_ENGINE=google
```

- Chave Google: https://console.cloud.google.com/apis/credentials
- Chave OpenAI: https://platform.openai.com/api-keys

---

## Idiomas suportados

Os idiomas ativos são definidos no mapa `LANGUAGES` em `translate-cli.js`:

| Locale  | Código | Idioma                  |
|---------|--------|-------------------------|
| `en_US` | `en`   | Inglês (Estados Unidos) |
| `es_ES` | `es`   | Espanhol (Espanha)      |
| `pt_BR` | `pt`   | Português (Brasil)      |

Outros idiomas (de_DE, fr_FR, it_IT, nl_NL, pt_PT, zh_CN) estão listados comentados
no mesmo mapa — basta descomentá-los para incluí-los nas próximas execuções.

> **Atenção — fonte mista de idioma.** O `.pot` contém strings em **idiomas diferentes**:
> strings legadas em PHP estão em português, e as strings novas do frontend estão em inglês.
> Por isso, prefira o motor de **IA** (`translate:ai`) para alvos não-inglês: ele detecta o
> idioma de origem por string. O Google usa `from:"en"` fixo e corrompe as strings em português.

---

## Comandos (scripts npm)

| Script                       | O que faz |
|------------------------------|-----------|
| `npm run pot`                | Gera/atualiza `joinotify.pot` varrendo o código-fonte. |
| `npm run translate`          | Traduz os `.po` (todos os idiomas) com **Google** (padrão). |
| `npm run translate:lang -- <locale>` | Traduz apenas um idioma (ex.: `pt_BR`). |
| `npm run translate:ai`       | Traduz com **OpenAI** (IA). |
| `npm run translate:ai:lang -- <locale>` | Traduz um idioma com IA. |
| `npm run translate:ai:retry` | Re-traduz com IA as entradas cuja tradução ficou idêntica à origem (veja abaixo). |
| `npm run compile:mo`         | Compila todos os `.po` → `.mo`. |
| `npm run compile:mo:lang -- <locale>` | Compila o `.mo` de um idioma. |
| `npm run compile:php`        | Compila todos os `.po` → `.l10n.php`. |
| `npm run compile:php:lang -- <locale>` | Compila o `.l10n.php` de um idioma. |

> Os scripts `pretranslate*` rodam `npm run pot` automaticamente antes de traduzir,
> garantindo que o `.pot` esteja sempre atualizado.

Os scripts CLI também aceitam flags diretas:

```bash
node translate-cli.js --engine=openai --lang=pt_BR
node translate-cli.js --engine=openai --retranslate-identical
node compile-mo-cli.js --lang pt_BR
node compile-php-cli.js pt_BR
```

---

## Procedimento completo (passo a passo)

### 1. Gerar o template (`.pot`)

```bash
npm run pot
```

`generate-pot-cli.js` é um parser próprio que varre `.php`, `.js`, `.jsx`, `.ts`, `.tsx`
e `.vue` em busca das funções de tradução do WordPress (`__`, `_e`, `_x`, `_n`,
`esc_html__`, `esc_attr__`, etc.), considerando **apenas** strings do text domain `joinotify`.
Diretórios como `node_modules`, `vendor`, `dist`, `release` e `examples` são ignorados.

> **Importante:** `release/` e `examples/` **devem** permanecer na lista `IGNORED_DIRECTORIES`.
> `release/` é uma cópia completa do plugin gerada no build — varrê-la duplica cada string e
> "ressuscita" strings já removidas do código (sintoma: contagem do `.pot` saltando para 3000+).

> **Não envolva identificadores técnicos em `__()`** — nomes de diretivas do `php.ini`
> (`memory_limit`, `max_input_vars`…), nomes de classes (`DOMDocument`) e constantes
> (`WP_DEBUG`) devem ser literais puras, senão a IA os traduz token a token
> (`memory_limit` → `limite_de_memória`).

### 2. Traduzir (`.po`)

```bash
npm run translate:ai          # todos os idiomas, via IA (recomendado)
npm run translate:ai:lang -- pt_BR   # somente pt_BR
```

`translate-cli.js` lê o `.pot`, carrega o `.po` existente de cada idioma e é **incremental**:
apenas entradas com `msgstr` **vazio** são reenviadas para tradução. Traduções já existentes
(inclusive edições manuais) são preservadas, e msgids obsoletos são descartados (o `.po` é
reconstruído a partir do `.pot` a cada execução).

#### Re-traduzir passagens idênticas (`--retranslate-identical`)

Uma entrada cujo `msgstr` é igual ao `msgid` (inglês deixado sem tradução por uma execução
anterior, ou devolvido inalterado pelo modelo para strings curtas/técnicas) é contada como
"pronta" e ficaria presa em inglês para sempre. A flag `--retranslate-identical` / `-r`
(ou `RETRANSLATE_IDENTICAL=1`, ou `npm run translate:ai:retry`) re-enfileira essas entradas
**apenas para alvos não-inglês**. Strings legitimamente idênticas (nomes de marca/país,
`Status`, `Prompt`) são reenviadas mas devolvidas inalteradas — custo desprezível.

### 3. Compilar artefatos de runtime

```bash
npm run compile:mo     # .po -> .mo
npm run compile:php    # .po -> .l10n.php
```

Você pode editar um `.po` à mão e recompilar sem retraduzir.
O `translate-cli.js` já escreve `.po`, `.mo`, `.l10n.php` e os `.json` de uma só vez;
os comandos `compile:*` existem para regenerar a partir de `.po` editados manualmente.

---

## Artefatos gerados (por idioma)

| Arquivo | Consumido por | Descrição |
|---------|---------------|-----------|
| `joinotify.pot` | tradutores | Template-mestre com todas as strings (sem traduções). |
| `joinotify-<locale>.po` | tradutores / build | Catálogo editável com as traduções. |
| `joinotify-<locale>.mo` | WordPress (PHP) | Binário gettext clássico. |
| `joinotify-<locale>.l10n.php` | WordPress 6.5+ (PHP) | Formato PHP — o WP o **prefere** ao `.mo`. |
| `joinotify-<locale>-<handle>.json` | JavaScript (Vue) | Um por *handle* de script, para `wp.i18n`. |

Os artefatos PHP são carregados via `load_plugin_textdomain` em `admin/src/Core/Init.php`.

### Handles de script JS

Os arquivos `.json` são gerados um para cada *handle* listado em `SCRIPT_HANDLES`
(`translate-cli.js`):

```
joinotify, joinotify-settings-app, joinotify-license-app,
joinotify-builder-app, joinotify-workflows-app, joinotify-history-app
```

Cada handle precisa corresponder a um script enfileirado em
`Assets\Settings_Assets::get_script_handle`. O WordPress 7.0 resolve o JSON pelo nome
`joinotify-<locale>-<handle>.json`, que coincide com a saída do pipeline; versões mais
antigas usavam `…-<md5(src)>.json`, e há um filtro `load_script_translation_file` em
`Settings_Assets` como rede de segurança.

> **Traduções de JS não exigem rebuild do Vite.** As strings de tradução são injetadas em
> runtime via `wp.i18n.setLocaleData` — após regenerar os `.json`, basta recarregar a página.

---

## Como funciona o carregamento no WordPress

**Lado PHP:** `load_plugin_textdomain('joinotify', false, '…/languages')` carrega o
`.l10n.php`/`.mo` do locale ativo. Funções `__()`, `_e()`, etc. passam a retornar as
traduções.

**Lado JS (Vue):** os componentes chamam `wp.i18n.__(text, 'joinotify')` (via
`app/src/utils/i18n.ts`). `Settings_Assets::enqueue_assets` registra
`wp_set_script_translations(handle, 'joinotify', languages_dir)` para cada handle, e o
WordPress injeta o `.json` correspondente como `setLocaleData` inline.

---

## Arquivos do pipeline

| Arquivo | Papel |
|---------|-------|
| `generate-pot-cli.js` | Extrai strings do código → `joinotify.pot`. |
| `translate-cli.js`    | Orquestra tradução incremental + escreve `.po`/`.mo`/`.l10n.php`/`.json`. |
| `openai-translate.js` | Motor de IA (OpenAI) via `fetch`; preserva `%s`, HTML, tokens `{{ ... }}`, URLs e marcas. |
| `l10n-php.js`         | Conversor compartilhado PO → `.l10n.php` (formato WP 6.5+). |
| `compile-mo-cli.js`   | Recompila `.mo` a partir de `.po`. |
| `compile-php-cli.js`  | Recompila `.l10n.php` a partir de `.po`. |
| `convert-cli.js`      | **Legado** (CommonJS, depende de `i18next-conv` externo) — substituído pelo escritor de JSON embutido em `translate-cli.js`. |
| `.env` / `.env.example` | Chaves de API (o `.env` é git-ignored). |

---

## Qual motor usar

| | **OpenAI (`translate:ai`)** | **Google (`translate`)** |
|---|---|---|
| Detecção de idioma de origem | Por string (lida com `.pot` misto PT/EN) | Fixa em `from:"en"` |
| Preserva `%s`, HTML, `{{ tokens }}` | Sim (instruído no prompt) | Parcial |
| Recomendado para | **Todos os alvos** (especialmente pt_BR) | Apenas se o `.pot` for 100% inglês |

Como o `.pot` do Joinotify tem origem mista (PT legado + EN novo), **prefira `translate:ai`**.

---

## Integração com o build de release

Durante `npm run build` (na raiz do plugin, via `scripts/build.mjs`), a etapa de
traduções roda automaticamente: `npm run pot` → (opcional `--translate`) → `compile:mo`
→ `compile:php`. Somente os artefatos compilados (`.po`, `.mo`, `.pot`, `.l10n.php`, `.json`)
são empacotados no ZIP de release — os scripts `*-cli.js` e o `node_modules` ficam de fora.

Para incluir a re-tradução por IA no build:

```bash
# na raiz do plugin
npm run build:translate     # = node scripts/build.mjs --translate (motor openai)
```
