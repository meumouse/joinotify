# Joinotify

**Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify.**

O Joinotify é um plugin para WordPress que permite criar **fluxos de automação de mensagens**
em um construtor visual de arrastar e soltar. Conecte gatilhos do seu site (pedidos do
WooCommerce, envios de formulários, ações de usuário, etc.) a ações como envio de mensagens
de WhatsApp, condições, atrasos e muito mais — tudo sem escrever código.

---

#### Licença

O Joinotify é **software livre**, distribuído sob a **GNU General Public License, versão 2 ou
posterior** — veja [`LICENSE`](LICENSE). Todos os recursos estão liberados: não há versão paga,
trial nem bloqueio por licença. O envio de mensagens depende de uma conta Joinotify, porque a
entrega passa pela API oficial do WhatsApp Cloud; os serviços externos utilizados estão
declarados em [`readme.txt`](readme.txt).

Joinotify® é uma marca da MEUMOUSE.COM® – SOLUÇÕES DIGITAIS LTDA. O plugin não é afiliado,
patrocinado nem endossado pela WhatsApp LLC ou pela Meta Platforms, Inc.

---

## Principais recursos

- **Construtor de fluxos visual** — interface em formato de canvas, com arrastar e soltar,
  conectar etapas, zoom, ajuste automático à tela e desfazer/refazer.
- **Mensagens de WhatsApp** — texto e mídia (com legenda e pré-visualização), formatação
  visual (negrito, itálico, emojis) convertida automaticamente para o padrão do WhatsApp.
- **Inteligência Artificial no construtor** — geração de fluxos completos a partir de uma
  descrição em texto, mensagens dinâmicas, variáveis inteligentes e snippets PHP assistidos.
- **Variáveis de texto** — placeholders `{{ ... }}` substituídos em tempo de envio, incluindo
  variáveis personalizadas criadas pelo usuário a partir de tipos de conteúdo e campos do site.
- **Condições e ramificações** — fluxos com lógica condicional (catálogo de condições por
  gatilho, seleção por lista e seletor de produtos).
- **Atrasos e agendamento** — tempo de espera com data/hora específicas, fila de
  processamento e reprocessamento de notificações que falharam.
- **Histórico de mensagens** — com filtros e seletor de data por mês e ano.
- **Login sem senha (OTP)** — código de verificação enviado via WhatsApp, com arquitetura
  pronta para novos canais.
- **Exportar/importar configurações** — todas as configurações do plugin em arquivo JSON.
- **Integrações** — WooCommerce, WooCommerce Subscriptions, WordPress (core), WPForms,
  Elementor e Flexify Checkout. Veja [`docs/integrations.md`](docs/integrations.md).
- **Extensível só com PHP** — terceiros adicionam ações, gatilhos, integrações, condições,
  placeholders, abas de configuração e rotas REST sem editar o core e sem JavaScript.
  Veja [`DEVELOPERS.md`](DEVELOPERS.md).
- **Multilíngue** — português, inglês e espanhol incluídos.

---

## Instalação

### Via painel de administração

1. Acesse o painel de administração do seu site WordPress.
2. Vá em **Plugins → Adicionar novo**.
3. Envie o arquivo ZIP do plugin (ou pesquise pelo nome) e clique em **Instalar agora**.
4. Clique em **Ativar plugin**.

### Via FTP

1. Baixe e descompacte o arquivo ZIP do plugin.
2. Conecte-se ao servidor via FTP e navegue até `wp-content/plugins`.
3. Envie a pasta descompactada do plugin.
4. No painel, vá em **Plugins → Plugins instalados**, localize o Joinotify e clique em **Ativar**.

### Requisitos

- WordPress **7.0+** (testado até 7.0) — a integração de IA usa o AI Client do core
- PHP **8.1+**

---

## Arquitetura

A partir da versão **2.0.0**, o Joinotify separa claramente frontend e backend:

```
joinotify/
├── joinotify.php          # Bootstrap do plugin (carrega o autoloader + Init)
├── admin/                 # Backend PHP (PSR-4, namespace MeuMouse\Joinotify\)
│   ├── src/               #   AI, Admin, Api, Assets, Builder, Core, Cron,
│   │                      #   Integrations, Notifications, Otp_Login, Rest, Validations, Views
│   └── vendor/            #   Dependências Composer (gerado no build)
├── app/                   # Frontend Vue 3 + Vite  → ver app/README.md
│   ├── src/               #   Apps por página (builder, workflows, settings, onboarding, history, otp-login)
│   └── dist/              #   Build de produção (gerado)
├── languages/             # Pipeline de i18n (Node) → ver languages/README.md
├── assets/                # Assets estáticos (marca, etc.)
├── templates/             # Templates PHP (ex.: login OTP)
├── docs/                  # Documentação adicional (integrations.md)
├── examples/              # Exemplo de extensão de terceiros
├── scripts/build.mjs      # Pipeline de build/empacotamento
├── scripts/deploy-svn.mjs # Publicação no SVN do WordPress.org
├── .wordpress-org/        # Arte da página do diretório (banner, ícone, screenshots)
├── DEVELOPERS.md          # API de extensão (PHP)
├── CHANGELOG.md          # Histórico de versões
└── LICENSE                # GNU GPL v2 ou posterior
```

- **Backend (PHP):** atua apenas como API (REST sob o namespace `joinotify/v1`) e fornecedor
  de esquemas de dados. Sem injeção de HTML nem jQuery.
- **Frontend (Vue):** consome tudo via REST; cada tela administrativa é uma aplicação Vue
  independente. Detalhes em [`app/README.md`](app/README.md).
- **Motor de fluxos:** os fluxos são uma árvore de nós (gatilho → ações/condições) salva no
  *post meta* `joinotify_workflow_content` do CPT `joinotify-workflow` e executada por
  `admin/src/Core/Workflow_Processor.php`.

---

## Desenvolvimento

Instale as dependências de cada parte:

```bash
# Frontend
cd app && npm install

# Traduções
cd ../languages && npm install

# Backend (na pasta admin)
cd ../admin && composer install
```

Durante o desenvolvimento do frontend:

```bash
cd app && npm run dev      # Vite com HMR
```

---

## Build e empacotamento

O pipeline completo é orquestrado por [`scripts/build.mjs`](scripts/build.mjs), a partir da
raiz do plugin:

```bash
npm install        # instala o archiver (dependência do build)
npm run build      # build completo + ZIP em release/joinotify-<versão>.zip
```

O build executa, em ordem:

1. **Frontend** — `app/` → `app/dist/` (Vite).
2. **Dependências PHP** — `composer install --no-dev` em `admin/` → `admin/vendor`.
3. **Traduções** — gera `.pot`, compila `.mo` e `.l10n.php` (`languages/`).
4. **Staging** — copia apenas os arquivos de runtime para `release/joinotify/`.
5. **ZIP** — empacota em `release/joinotify-<versão>.zip` (pronto para upload no WordPress).

### Scripts de build

| Comando | Descrição |
|---------|-----------|
| `npm run build` | Build completo + ZIP. |
| `npm run build:fast` | Reaproveita artefatos existentes (pula app, composer e traduções). |
| `npm run build:translate` | Re-traduz os `.po` via IA antes de compilar (requer `OPENAI_API_KEY`). |
| `npm run build:app` | Apenas o build do frontend (`app/dist`). |

Flags úteis do `build.mjs`: `--skip-app`, `--skip-composer`, `--skip-translations`,
`--translate`, `--engine=<nome>`, `--no-install`, `--no-zip`, `--ship-locales`.

O build recusa rodar quando o `joinotify.php` (header e `$plugin_version`), o `Stable tag` do
`readme.txt` e o `package.json` não declaram a mesma versão.

### Traduções no pacote

Só o `joinotify.pot` **vai no ZIP**. O WordPress.org gera e entrega cada locale pelo
[translate.wordpress.org](https://translate.wordpress.org/), e a equipe de revisão pede que o pacote
não duplique esse canal — por isso os catálogos compilados (`.po`/`.mo`/`.l10n.php`/`.json`) ficam
de fora. Até as strings serem importadas e aprovadas por lá, instalações não-inglesas caem para o
inglês.

A flag `--ship-locales` gera um pacote com os locales compilados, que é o que instalações fora do
diretório precisam — elas não recebem *language packs*.

---

## Publicação no WordPress.org

O Git continua sendo o histórico de desenvolvimento. O **SVN é só o canal de publicação**, e o ciclo
inteiro está em [`scripts/deploy-svn.mjs`](scripts/deploy-svn.mjs).

```bash
npm run deploy     # ensaio: prepara tudo e mostra o diff, sem publicar nada
```

Nada vai ao ar sem `--commit`. Confirmado o diff:

```bash
node scripts/deploy-svn.mjs --skip-build --commit
```

| Comando | Descrição |
|---------|-----------|
| `npm run deploy` | Build + espelhamento em `trunk/` + tag, tudo local. Não publica. |
| `npm run deploy:commit` | O mesmo, publicando `trunk/` e `tags/<versão>` em uma única revisão. |
| `npm run deploy:assets` | Só a arte da página (`.wordpress-org/` → `assets/` do SVN). |
| `npm run deploy:trunk` | Atualiza `trunk/` sem criar tag — para correções só de `readme.txt`. |

O script mantém a cópia de trabalho em `.wporg-svn/` (ignorada pelo Git), com `tags/` em
profundidade rasa: os nomes das tags bastam para detectar duplicata, e baixar o conteúdo de todas
elas custaria centenas de megabytes.

Requisitos: cliente `svn` no PATH e a variável `WPORG_USERNAME` (ou `--username=<nome>`) com o
usuário do WordPress.org.

> **`assets/` do SVN ≠ `assets/` do plugin.** A do SVN fica ao lado de `trunk/`, fora do pacote
> instalado, e guarda banner, ícone e capturas de tela — veja
> [`.wordpress-org/README.md`](.wordpress-org/README.md). A `assets/brand/` interna continua indo
> dentro do ZIP normalmente.

---

## Documentação

| Documento | Conteúdo |
|-----------|----------|
| [`app/README.md`](app/README.md) | Frontend Vue 3 + Vite: stack, entries, bootstrap, builder, i18n. |
| [`languages/README.md`](languages/README.md) | Pipeline de i18n: geração de `.pot`, tradução (IA/Google), compilação. |
| [`DEVELOPERS.md`](DEVELOPERS.md) | API de extensão em PHP (ações, gatilhos, integrações, condições, placeholders, REST). |
| [`docs/integrations.md`](docs/integrations.md) | Integrações disponíveis e seus gatilhos. |
| [`CHANGELOG.md`](CHANGELOG.md) | Histórico completo de versões. |
| [`LICENSE`](LICENSE) | GNU GPL v2 ou posterior. |

---

## Histórico de versões

O registro completo de alterações está em [`CHANGELOG.md`](CHANGELOG.md).

---

© 2026 MeuMouse.com — Soluções Digitais LTDA. Todos os direitos reservados.
