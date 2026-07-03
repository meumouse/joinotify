# Contribuindo com o Joinotify

Este guia é o ponto de partida para **desenvolvedores humanos e agentes de IA** que vão
trabalhar no código do Joinotify. Ele reúne a organização do projeto, as convenções de código,
o fluxo de trabalho com Git e as regras que mantêm o plugin consistente.

> **Software proprietário.** O Joinotify é propriedade da MEUMOUSE.COM® — Soluções Digitais LTDA.
> Não é um projeto de código aberto: contribuições externas via fork/PR público **não** são
> aceitas. Este documento orienta a equipe interna e colaboradores autorizados. Veja
> [`license.md`](license.md).

## Mapa da documentação

Antes de codar, saiba onde cada coisa está documentada. **Não duplique** estes conteúdos aqui —
linke para eles.

| Documento | Quando consultar |
|-----------|------------------|
| [`README.md`](README.md) | Visão geral, arquitetura, instalação, build/empacotamento. |
| [`DEVELOPERS.md`](DEVELOPERS.md) | API de extensão em PHP (ações, gatilhos, integrações, condições, placeholders, REST, canais de notificação/OTP). |
| [`app/README.md`](app/README.md) | Frontend Vue 3 + Vite: entries, bootstrap REST, store do builder, Tailwind, i18n. |
| [`languages/README.md`](languages/README.md) | Pipeline de i18n (geração de `.pot`, tradução IA/Google, compilação `.mo`/`.l10n.php`/`.json`). |
| [`docs/integrations.md`](docs/integrations.md) | Integrações disponíveis e seus gatilhos. |
| [`changelogs.md`](changelogs.md) | Histórico de versões. |

---

## 1. Organização do projeto

A partir da **2.0.0** há separação estrita entre backend (PHP, só REST + esquemas) e frontend
(Vue, consome tudo via REST). **Não existe injeção de HTML server-side nem jQuery.**

```
joinotify/
├── joinotify.php          # Bootstrap: carrega autoloader Composer + instancia Core\Init
├── admin/                 # BACKEND PHP (PSR-4, namespace MeuMouse\Joinotify\)
│   ├── src/
│   │   ├── AI/            #   Geração de fluxos por IA
│   │   ├── Admin/         #   Telas/settings/builder server-side, Workflow_Migrator
│   │   ├── Api/           #   Controller de envio, Extensions (facade de extensão)
│   │   ├── Assets/        #   Registro de assets
│   │   ├── Builder/       #   Catálogo de ações, Placeholders, Triggers
│   │   ├── Core/          #   Init, Workflow_Processor (motor), Helpers, Upgrader, Cron...
│   │   ├── Cron/          #   Tarefas agendadas (WP-Cron)
│   │   ├── Integrations/  #   WooCommerce, WPForms, Elementor, Flexify Checkout...
│   │   ├── Notifications/ #   Camada de canais de notificação (Channel_Interface)
│   │   ├── Otp_Login/     #   Login sem senha (canais OTP)
│   │   ├── Rest/          #   Rotas REST (namespace joinotify/v1)
│   │   ├── Validations/   #   Condições e validações de fluxo
│   │   └── Views/         #   Views PHP residuais
│   └── vendor/            #   Dependências Composer (gerado no build — não versionado)
├── app/                   # FRONTEND Vue 3 + Vite (ver app/README.md)
│   ├── src/               #   entries, pages, components, builder, stores, serializers...
│   └── dist/              #   Build de produção (gerado — não versionado)
├── languages/             # Pipeline de i18n em Node (ver languages/README.md)
├── templates/             # Templates PHP (ex.: login OTP) — markup escaneado pelo Tailwind
├── assets/                # Assets estáticos (marca, ícones)
├── dist/                  # Templates de fluxo distribuídos + update-checker.json
├── docs/                  # Documentação adicional
├── examples/              # Exemplo de extensão de terceiros (PHP)
├── tests/                 # Harnesses de teste standalone + fixtures
├── scripts/build.mjs      # Pipeline de build/empacotamento (orquestra tudo)
└── *.md                   # README, DEVELOPERS, changelogs, license, este arquivo
```

### O contrato central: o fluxo (workflow)

Entender isto resolve 80% das tarefas:

- Um fluxo é uma **árvore de nós** (gatilho → ações/condições) salva no *post meta*
  `joinotify_workflow_content` do CPT `joinotify-workflow`.
- O **frontend** modela isso como árvore aninhada de `WorkflowNode` na store Pinia
  [`useWorkflowBuilderStore.ts`](app/src/stores/useWorkflowBuilderStore.ts), serializa via
  [`workflowSerializer.ts`](app/src/serializers/workflowSerializer.ts) e re-hidrata via
  [`workflowParser.ts`](app/src/parsers/workflowParser.ts).
- O **backend** consome exatamente esse formato e o executa em
  [`Core/Workflow_Processor.php`](admin/src/Core/Workflow_Processor.php).

> Frontend e backend compartilham o **mesmo contrato de dados** (`workflow_content`). Ao mexer
> na forma de um nó, atualize **os dois lados** (serializer/parser no Vue **e** o processador no
> PHP), senão fluxos salvos quebram. Considere também o [`Workflow_Migrator`](admin/src/Admin/Builder/Workflow_Migrator.php)
> para conteúdos legados.

---

## 2. Ambiente e setup

**Requisitos:** PHP **7.4+**, Node.js **18+**, Composer.

```bash
# Frontend
cd app && npm install

# Traduções (pipeline Node)
cd ../languages && npm install

# Backend (dependências Composer)
cd ../admin && composer install

# Build/empacotamento (na raiz)
cd .. && npm install
```

Desenvolvimento do frontend com HMR:

```bash
cd app && npm run dev
```

Build completo + ZIP de release (detalhes e flags em [`README.md`](README.md#build-e-empacotamento)):

```bash
npm run build          # build completo → release/joinotify-<versão>.zip
npm run build:fast     # reaproveita artefatos (pula app/composer/traduções)
npm run build:app      # só o frontend
```

---

## 3. Convenções de código — Backend (PHP)

Siga o estilo já presente em `admin/src/`. **Combine com o código ao redor** antes de impor
qualquer preferência pessoal.

- **PSR-4 + WordPress Coding Standards.** Namespace raiz `MeuMouse\Joinotify\`, mapeado para
  `admin/src/`. Um arquivo por classe; o nome do arquivo casa com o nome da classe
  (`Workflow_Processor.php` → `class Workflow_Processor`).
- **Indentação por TAB** (não espaços), conforme todo o backend.
- **Guarda de segurança** no topo de todo arquivo PHP: `defined('ABSPATH') || exit;`.
- **Sintaxe de array longa** (`array( ... )`) no backend, como no código existente
  (os exemplos curtos em `DEVELOPERS.md` usam `[]`, mas o core usa `array()`).
- **Espaços dentro de parênteses** em chamadas de função: `current_user_can( 'manage_options' )`.
- **Nomes:** classes em `Studly_Snake_Case` (`Phone_Manager`), métodos/variáveis/funções em
  `snake_case`.
- **Docblocks obrigatórios** em classes e métodos públicos, com as tags do padrão do projeto:

  ```php
  /**
   * Resumo curto em uma linha.
   *
   * @since 1.0.0
   * @version 2.0.0
   * @param string $sender Sender phone in digits.
   * @return bool
   */
  ```

  - `@since` = versão em que o símbolo **surgiu** (nunca mude depois).
  - `@version` = versão da **última alteração** (atualize ao mexer no método/classe).
  - Use a versão atual do plugin (veja `joinotify.php` → `Version:`).
- **Sem editar o core para estender.** Funcionalidades novas de domínio devem passar pelos
  filtros/facade documentados em [`DEVELOPERS.md`](DEVELOPERS.md). Hooks do plugin seguem o
  padrão de nome `Joinotify/Area/Nome` (ex.: `Joinotify/Builder/Actions`).
- **Handlers de ação retornam `bool`.** Retornar não-bool pode interromper o funil.
- **i18n:** toda string voltada ao usuário passa por `__()`, `esc_html__()`, etc., com o text
  domain `'joinotify'`.

### Helpers de runtime

Prefira os helpers globais `joinotify_*()` (ex.: `joinotify_replace_placeholders()`,
`joinotify_send_whatsapp_message_text()`) a acoplar diretamente a classes com namespace. A tabela
completa está em [`DEVELOPERS.md` → Runtime helpers](DEVELOPERS.md#runtime-helpers).

---

## 4. Convenções de código — Frontend (Vue 3 + TS)

Detalhes completos de arquitetura em [`app/README.md`](app/README.md). Resumo das convenções:

- **Vue 3 com `<script setup>` + Composition API.** TypeScript estrito.
- **Estado em Pinia** (`stores/`). A store do builder é a fonte da verdade da árvore do fluxo.
- **Tailwind CSS 3** para estilo; `important: true` está ligado para vencer o CSS do admin do
  WordPress. Evite CSS solto quando uma utilitária do Tailwind resolve.
- **Alias `@/*` → `src/*`** (definido em [`app/tsconfig.json`](app/tsconfig.json)). Use-o em
  vez de caminhos relativos longos.
- **Multi-page:** cada tela é um app Vue independente em `src/entries/<page>.js` que chama
  `mountPage('<handle>', PageComponent)`. Ao criar uma página nova, registre o entry no
  `vite.config.js` e o handle de script correspondente no backend.
- **Comunicação só via REST** sob `joinotify/v1`, usando o cliente em
  [`utils/api.js`](app/src/utils/api.js) com o nonce do WordPress. Nada de payload pesado embutido
  no HTML — o bootstrap é GET assíncrono (ver `utils/bootstrap.js`).
- **i18n:** use `wp.i18n.__(text, 'joinotify')` via [`utils/i18n.ts`](app/src/utils/i18n.ts).
  Strings traduzíveis precisam usar as funções de `@wordpress/i18n` para serem capturadas pelo
  extrator `.pot`.

> **Regra de ouro do builder:** adicionar uma **ação** ou um **gatilho** normalmente **não exige
> mexer no frontend** — registre no backend pelos filtros de [`DEVELOPERS.md`](DEVELOPERS.md) e a
> biblioteca de ações renderiza o `settings_schema` sozinha (zero JS por ação). Só toque no Vue
> quando precisar de um componente de configuração realmente customizado.

### Cache-busting de assets

`app.js`/CSS são gerados **sem hash**; o backend versiona pelo `filemtime` (`?ver=...`). Se uma
mudança de frontend "não aparece" após o build, suspeite primeiro do cache de assets (force
refresh / rebuild).

---

## 5. Internacionalização (i18n)

- Pipeline completo em [`languages/README.md`](languages/README.md). Text domain: `joinotify`.
- **Mudanças só de tradução não exigem rebuild do frontend** — as traduções de JS são injetadas
  em runtime via `wp.i18n.setLocaleData` por handle de script.
- Ao **adicionar/alterar strings** no código: rode `npm run pot` em `languages/` para regenerar o
  `.pot`, depois traduza (`npm run translate` / `:ai`) e compile (`compile:mo`, `compile:php`).
- Idiomas mantidos: **pt_BR, en_US, es_ES**.

---

## 6. Fluxo de trabalho com Git

- **Branches:** trabalhe em branch de feature/correção a partir de `main` (a branch de release
  atual é `update-2.0.0`). **Não** comite direto na `main`.
- **Conventional Commits** (padrão já usado no histórico):

  ```
  <tipo>(<escopo>): resumo no imperativo

  feat(queue): add processing-queue subpage for scheduled segments
  fix(cron): store positional args for WP-Cron scheduled segments
  docs: add language pipeline documentation
  ```

  Tipos em uso: `feat`, `fix`, `refactor`, `style`, `docs`, `chore`, `i18n`. Escopos comuns:
  `builder`, `ai`, `cron`, `queue`, `history`, `settings`, `core`, `i18n`.
- **Mensagens em inglês**, curtas e no imperativo. A documentação narrativa (`*.md`) é em
  português.
- **Não comite artefatos gerados nem segredos.** Já estão no [`.gitignore`](.gitignore):
  `app/dist/`, `admin/vendor`, `node_modules/`, `release/`, `.env`, `composer.lock`,
  `package-lock.json`.
- **Commits/pushes só quando solicitado.** Agentes de IA: não faça commit/push sem pedido
  explícito do usuário.

---

## 7. Testes e verificação

Os testes em `tests/` são **harnesses standalone** (sem PhpUnit, sem bootstrap do WordPress),
projetados para rodar com o PHP local:

```bash
# Exemplo (ajuste o caminho do php.exe do seu ambiente Local/XAMPP)
& "C:\caminho\para\php.exe" tests/workflow-migrator-test.php
```

- [`tests/workflow-migrator-test.php`](tests/workflow-migrator-test.php) — migração de fluxos
  legados (usa fixtures em `tests/fixtures/`).
- [`tests/upgrader-test.php`](tests/upgrader-test.php) — gerenciador central de upgrade.
- [`tests/schedule-cron-args-test.php`](tests/schedule-cron-args-test.php) — args posicionais de
  WP-Cron.

**Antes de concluir uma alteração:**

1. Rode o(s) harness(es) relevante(s) se você mexeu em migração, upgrade ou cron.
2. Para mudanças de frontend, rode `npm run build:app` (ou `npm run dev`) e confirme que compila
   sem erro de TypeScript.
3. Para mudanças que afetam o ZIP de release, rode `npm run build` e confirme que o pacote é
   gerado.
4. Se adicionou/alterou strings, regenere o `.pot` (seção i18n).

Reporte resultados com honestidade: se um teste falhou, diga e mostre a saída.

---

## 8. Checklist de Pull Request / entrega

- [ ] Código combina com o estilo do arquivo/módulo vizinho (TAB no PHP, convenções Vue/TS no app).
- [ ] Docblocks PHP atualizados (`@since`/`@version`) nos símbolos novos/alterados.
- [ ] Mudança no contrato de fluxo refletida **nos dois lados** (serializer/parser + processador
      PHP) e, se preciso, no migrator.
- [ ] Strings novas passam por `__()`/`wp.i18n.__` e o `.pot` foi regenerado.
- [ ] Extensão de funcionalidade feita via filtros/facade — **sem editar o core** quando evitável.
- [ ] Sem artefatos gerados (`dist/`, `vendor/`, `node_modules/`) nem segredos no commit.
- [ ] Testes relevantes rodados; build verde quando aplicável.
- [ ] Commit no padrão Conventional Commits.
- [ ] `changelogs.md` atualizado para mudanças voltadas ao usuário.

---

## 9. Notas específicas para agentes de IA

- **Leia antes de escrever.** Os contratos centrais (workflow tree, REST `joinotify/v1`, hooks
  `Joinotify/...`) são interdependentes. Antes de editar, localize o lado correspondente
  (frontend ↔ backend) e o migrator.
- **Prefira estender a editar o core.** Quase tudo (ações, gatilhos, integrações, condições,
  placeholders, settings, REST, canais) é extensível via PHP por filtros — ver
  [`DEVELOPERS.md`](DEVELOPERS.md) e o exemplo em
  [`examples/joinotify-extension-example.php`](examples/joinotify-extension-example.php).
- **Não invente APIs.** Use os helpers `joinotify_*()` e classes que realmente existem; confirme
  com busca no repositório antes de referenciar um símbolo.
- **Idiomas:** código/identificadores e mensagens de commit em inglês; documentação narrativa em
  português (combine com o arquivo que está editando).
- **Não comite, não faça push e não rode builds destrutivos** sem pedido explícito do usuário.
- **Windows / Local.** O ambiente de dev é WordPress via Local (Windows). Caminhos de `php.exe`
  variam por instalação — não assuma um caminho fixo.

---

© 2026 MeuMouse.com — Soluções Digitais LTDA. Todos os direitos reservados.
