# Joinotify — Frontend (Vue 3 + Vite)

Este diretório contém o **frontend do plugin Joinotify**: as aplicações Vue 3 que renderizam
as telas administrativas (construtor de fluxos, lista de fluxos, configurações, licença,
histórico) e a tela pública de login por OTP.

A partir da versão **2.0.0**, o Joinotify adota uma arquitetura com **separação clara entre
frontend e backend**: o PHP (`admin/src`, namespace `MeuMouse\Joinotify\`) atua apenas como
backend (REST + esquemas de dados), e este app Vue consome tudo via REST. Não há injeção de
HTML nem jQuery.

---

## Stack

| Camada            | Tecnologia |
|-------------------|------------|
| Framework         | Vue 3 (`<script setup>`, Composition API) |
| Build             | Vite 6 |
| Estado            | Pinia |
| Canvas de fluxos  | [@vue-flow](https://vueflow.dev/) (core, background, controls, minimap) |
| Estilos           | Tailwind CSS 3 + PostCSS |
| UI utilitária     | `@headlessui/vue`, `@boxicons/vue`, `vue3-emoji-picker`, `intl-tel-input` |
| i18n              | `@wordpress/i18n` (`wp.i18n`) |
| Tipagem           | TypeScript 5 (estrita) |

---

## Requisitos

- **Node.js 18+**

## Instalação

```bash
cd app
npm install
```

## Comandos

| Comando         | O que faz |
|-----------------|-----------|
| `npm run dev`   | Servidor de desenvolvimento do Vite (HMR). |
| `npm run build` | Build de produção → `app/dist/`. |

Da raiz do plugin também é possível disparar só o build do frontend:

```bash
npm run build:app    # = npm run build --prefix app
```

---

## Múltiplos pontos de entrada (multi-page)

Cada tela do plugin é uma aplicação Vue independente, com seu próprio *entry* em
`src/entries/`. O Vite faz o build de todos eles (config em [`vite.config.js`](vite.config.js)):

| Entry                | Página                       | Handle de script        |
|----------------------|------------------------------|-------------------------|
| `settings.js`        | Configurações                | `joinotify-settings-app` |
| `license.js`         | Licença                      | `joinotify-license-app`  |
| `builder.js`         | Construtor de fluxos (canvas)| `joinotify-builder-app`  |
| `workflows.js`       | Lista de fluxos              | `joinotify-workflows-app`|
| `history.js`         | Histórico de mensagens       | `joinotify-history-app`  |
| `otp-login.js`       | Login por OTP (público)      | —                        |

Cada entry importa estilos, importa o componente de página e chama
`mountPage('<handle>', PageComponent)`:

```js
// src/entries/builder.js
import '../styles/main.css';
import '../pages/builder/styles.css';
import BuilderPage from '../pages/builder/BuilderPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('joinotify-builder-app', BuilderPage);
```

### Saída do build

`vite.config.js` define nomes de arquivo previsíveis:

- Script de cada entry: `dist/<entry>/app.js` (**sem hash**)
- CSS: `dist/styles/<name>.css` (**sem hash**)
- Chunks/assets: `dist/chunks/[name]-[hash].js`, `dist/assets/[name]-[hash][ext]`
- Manifesto: `dist/.vite/manifest.json` (lido pelo backend em `Core/Scripts.php`)

> **Cache-busting:** como o `app.js`/CSS não têm hash, o backend versiona os assets pelo
> `filemtime` do arquivo em `dist/` (`?ver=...`). Se uma alteração de frontend "não aparece"
> no navegador após o build, suspeite primeiro do cache de assets.

---

## Bootstrap e comunicação com o backend

O carregamento de dados é **baseado em REST via GET** (o payload pesado não fica embutido no
HTML). `utils/bootstrap.js` (`mountPage`) é **assíncrono**:

1. Lê `window.joinotifyBootstrapConfig` (injetado por `wp_localize_script`), que contém
   `restUrl`, `nonce`, `page` e `endpoint`.
2. Faz GET no endpoint de bootstrap (o skeleton permanece até resolver).
3. Monta a aplicação Vue com o payload recebido.
4. *Fallback* para o atributo legado `data-bootstrap` apenas se o config global não existir.

Mapa de endpoints de bootstrap:

| Página              | Endpoint REST |
|---------------------|---------------|
| settings / license  | `admin/settings` |
| builder             | `admin/builder?id=N` |
| workflows           | `admin/workflows/bootstrap` |

As chamadas subsequentes usam o cliente em `utils/api.js` (`createApiClient`), sempre sob o
namespace REST `joinotify/v1`, com o nonce do WordPress.

---

## Estrutura de pastas (`src/`)

```
src/
├── entries/          # Pontos de entrada do Vite (um por página)
├── pages/            # Componente raiz de cada página (builder, workflows, settings, license, history)
├── components/       # Componentes reutilizáveis
│   ├── base/         # Primitivos (botões, selects, inputs...)
│   ├── flow/         # Canvas @vue-flow: FlowCanvas, FlowNode, FlowEdge
│   ├── settings/     # Cartões/campos da tela de configurações
│   ├── workflows/    # UI da lista de fluxos
│   ├── modals/, toasts/, toggles/, tooltips/, skeletons/, cards/, fields/, brand/
├── builder/          # Lógica do construtor de fluxos
│   └── actions/      # Catálogo de ações
│       ├── definitions/  # Definições de ação no frontend (ex.: whatsappText.ts)
│       ├── settings/     # Componentes de configuração de cada ação (drawer)
│       ├── components/   # ActionLibraryModal, ActionLibraryCard...
│       ├── registry/     # Registro/normalização de definições de ação
│       └── composables/, utils/
├── registries/       # actionRegistry, triggerRegistry, triggerContexts
├── stores/           # Pinia — useWorkflowBuilderStore.ts (árvore de nós do fluxo)
├── serializers/      # workflowSerializer.ts (árvore Vue → workflow_content)
├── parsers/          # workflowParser.ts (workflow_content → árvore Vue)
├── services/         # workflowApi.ts (chamadas REST do builder)
├── composables/      # useWorkflows, useWorkflowBuilder, useMessageHistory, usePagination...
├── config/           # settingsSections.js (esquema das seções de configuração)
├── otp-login/        # App público de login por OTP + componentes
├── utils/            # bootstrap.js, api.js, i18n.ts, html.ts, triggerSettings.ts, workflowTree.ts...
├── types/            # Tipos TS (workflow.ts, workflowBuilder.ts)
└── styles/           # main.css (Tailwind)
```

---

## O construtor de fluxos (builder)

É a tela central do plugin. Caminho de renderização:

```
entries/builder.js
  → pages/builder/BuilderPage.vue
    → BuilderShell → BuilderCanvasView
      → components/flow/FlowCanvas.vue  (@vue-flow)
```

- **Estado:** Pinia `stores/useWorkflowBuilderStore.ts` guarda o fluxo como uma **árvore
  aninhada de `WorkflowNode`** (`{ id, type, data, children, branches? }`). O grafo do
  vue-flow é derivado a cada render; posições e arestas persistem como metadados do editor
  dentro de `node.data` (`canvas_position`, `connection_from`, `connection_mode`...).
- **Serialização:** `serializers/workflowSerializer.ts` escreve o `workflow_content`
  (gatilho primeiro; condições usam `children: { action_true, action_false }`), salvo no
  *post meta* `joinotify_workflow_content`. O PHP consome exatamente esse contrato em
  `Core/Workflow_Processor.php`.
- **Catálogo de ações:** ações têm `category` + `context`. A biblioteca de ações é um modal
  (`ActionLibraryModal.vue`) com abas por categoria, vindas do backend
  (filtro `Joinotify/Builder/Action_Categories`). A configuração de cada ação fica num
  *drawer* lateral, renderizado a partir do `settings_schema` (zero JS por ação).
- **Gatilhos com configuração obrigatória:** um gatilho pode declarar um `settings` (schema
  de campos) no backend; o frontend renderiza no drawer e sinaliza quando há configuração
  pendente.

> Adicionar uma ação ou um gatilho normalmente **não exige mudança no frontend** — basta
> registrar no backend via os filtros documentados em [`../DEVELOPERS.md`](../DEVELOPERS.md).

---

## Internacionalização (i18n)

Os componentes usam `wp.i18n.__(text, 'joinotify')` através de `utils/i18n.ts`. As traduções
de JS são injetadas em runtime pelo WordPress (`wp.i18n.setLocaleData`), portanto
**alterações apenas de tradução não exigem rebuild** — veja o pipeline em
[`../languages/README.md`](../languages/README.md).

Strings traduzíveis devem usar as funções de `@wordpress/i18n` para serem capturadas pelo
extrator `.pot`. Cada handle de script (lista acima) tem um arquivo `.json` de tradução
correspondente.

---

## Tailwind

Configuração em [`tailwind.config.js`](tailwind.config.js). Pontos relevantes:

- `important: true` — necessário para vencer a especificidade do CSS do admin do WordPress.
- `content` inclui `./src/**/*.{vue,js}` **e** `../templates/otp-login/**/*.php` (o login OTP
  tem markup em PHP).
- Paleta customizada: `primary` (azul `#0088ff`), `shell`, além de `success`/`danger`/
  `warning`/`info`.

---

## Notas

- `dist/` e `node_modules/` são **ignorados pelo Git**; o `dist/` é gerado no build e
  empacotado no ZIP de release pelo `scripts/build.mjs` na raiz.
- Alias TypeScript: `@/*` → `src/*` (definido em [`tsconfig.json`](tsconfig.json)).
- Para o ciclo completo (frontend + PHP + traduções + ZIP), use os scripts de build da raiz
  do plugin — veja o [`README.md`](../README.md) principal.
