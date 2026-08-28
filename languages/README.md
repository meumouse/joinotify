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
        │  npm run compile:json   │
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
| `npm run compile:json`       | Compila todos os `.po` → `.json` por handle de script. |
| `npm run compile:json:lang -- <locale>` | Compila os `.json` de um idioma. |

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
npm run compile:json   # .po -> .json (um por handle de script)
```

Você pode editar um `.po` à mão e recompilar sem retraduzir.
O `translate-cli.js` já escreve `.po`, `.mo`, `.l10n.php` e os `.json` de uma só vez;
os comandos `compile:*` existem para regenerar a partir de `.po` editados manualmente.

> `compile:json` lê as traduções do `.po`, mas lê as **referências de origem** (`#:`) do
> `joinotify.pot`. Um `.po` só é reescrito por uma rodada de tradução, então suas referências
> ficam desatualizadas assim que uma string muda de arquivo; o `.pot` é regenerado a cada build.

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

A lista de handles **não é mantida à mão**: `js-module-graph.js` varre `app/src/entries/*`
(os mesmos entries declarados em `vite.config.js`) e lê o handle de cada um da chamada
`mountPage('<handle>', …)`. Entries sem `mountPage` — hoje só `otp-login.js`, cujo handle
vive em `Otp_Login\Frontend_Assets::HANDLE` — são resolvidos pelo mapa `HANDLE_OVERRIDES`
do mesmo arquivo. Um entry sem handle **interrompe a geração**, em vez de produzir um
pacote em que a página nova aparece sem tradução.

Handles atuais:

```
joinotify-settings-app, joinotify-onboarding-app, joinotify-builder-app,
joinotify-workflows-app, joinotify-history-app, joinotify-queue-app,
joinotify-otp-login
```

O WordPress resolve o JSON pelo nome `joinotify-<locale>-<handle>.json`, que coincide com a
saída do pipeline; o core, porém, procura `…-<md5(src)>.json`, então o filtro
`load_script_translation_file` (`Settings_Assets::resolve_script_translation_file`, registrado
também por `Otp_Login\Frontend_Assets` para o front-end) faz o remapeamento.

#### Cada JSON carrega só as suas strings

Cada `.json` contém apenas as strings que o *seu* bundle pode pedir em runtime — a mesma
regra do `wp i18n make-json`. `script-translations.js` resolve cada string de volta para os
arquivos listados nos comentários `#:` do `.pot` e a inclui somente nos handles cujo grafo de
imports contém um desses arquivos. Strings extraídas apenas de PHP não entram em nenhum JSON.

O grafo de imports vem do código-fonte, não do `app/dist/.vite/manifest.json`: o manifest lista
os *chunks* que um entry carrega, mas um chunk compartilhado (`_PageHeader-<hash>.js`) não nomeia
nenhum módulo de origem, então o mapeamento pararia na fronteira do bundle.

> Antes disso, todo handle recebia uma cópia idêntica da tabela inteira (1740 strings, ~172 KB
> cada). Hoje o maior é o `builder-app` (~450 strings) e o `queue-app` fica em ~42.

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
| `js-module-graph.js`  | Descobre os entries do Vite, resolve o handle de cada um e percorre o grafo de imports. |
| `script-translations.js` | Escritor compartilhado dos `.json` por handle (usado por `translate-cli.js` e `compile-json-cli.js`). |
| `compile-mo-cli.js`   | Recompila `.mo` a partir de `.po`. |
| `compile-php-cli.js`  | Recompila `.l10n.php` a partir de `.po`. |
| `compile-json-cli.js` | Recompila os `.json` por handle a partir de `.po` + `.pot`. |
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
→ `compile:php` → `compile:json`. Somente os artefatos compilados (`.po`, `.mo`, `.pot`, `.l10n.php`, `.json`)
são empacotados no ZIP de release — os scripts `*-cli.js` e o `node_modules` ficam de fora.

Para incluir a re-tradução por IA no build:

```bash
# na raiz do plugin
npm run build:translate     # = node scripts/build.mjs --translate (motor openai)
```
