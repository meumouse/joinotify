# Joinotify

**Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify.**

O Joinotify é um plugin para WordPress que permite criar **fluxos de automação de mensagens**
em um construtor visual de arrastar e soltar. Conecte gatilhos do seu site (pedidos do
WooCommerce, envios de formulários, ações de usuário, etc.) a ações como envio de mensagens
de WhatsApp, condições, atrasos e muito mais — tudo sem escrever código.

---

#### Propriedade intelectual

O software Joinotify ® é uma propriedade registrada da MEUMOUSE.COM® – SOLUÇÕES DIGITAIS LTDA,
em conformidade com o §2°, art. 2° da Lei 9.609, de 19 de Fevereiro de 1998.
É expressamente proibido a distribuição ou cópia ilegal deste software, sujeita a penalidades
conforme as leis de direitos autorais vigentes. Consulte o arquivo [`license.md`](license.md).

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

- WordPress (testado até 7.0)
- PHP **7.4+**

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
│   ├── src/               #   Apps por página (builder, workflows, settings, license, history, otp-login)
│   └── dist/              #   Build de produção (gerado)
├── languages/             # Pipeline de i18n (Node) → ver languages/README.md
├── assets/                # Assets estáticos (marca, etc.)
├── templates/             # Templates PHP (ex.: login OTP)
├── docs/                  # Documentação adicional (integrations.md)
├── examples/              # Exemplo de extensão de terceiros
├── scripts/build.mjs      # Pipeline de build/empacotamento
├── DEVELOPERS.md          # API de extensão (PHP)
├── changelogs.md          # Histórico de versões
└── license.md             # Licença proprietária
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
`--translate`, `--engine=<nome>`, `--no-install`, `--no-zip`.

---

## Documentação

| Documento | Conteúdo |
|-----------|----------|
| [`app/README.md`](app/README.md) | Frontend Vue 3 + Vite: stack, entries, bootstrap, builder, i18n. |
| [`languages/README.md`](languages/README.md) | Pipeline de i18n: geração de `.pot`, tradução (IA/Google), compilação. |
| [`DEVELOPERS.md`](DEVELOPERS.md) | API de extensão em PHP (ações, gatilhos, integrações, condições, placeholders, REST). |
| [`docs/integrations.md`](docs/integrations.md) | Integrações disponíveis e seus gatilhos. |
| [`changelogs.md`](changelogs.md) | Histórico completo de versões. |
| [`license.md`](license.md) | Termos de licença. |

---

## Histórico de versões

O registro completo de alterações está em [`changelogs.md`](changelogs.md).

---

© 2026 MeuMouse.com — Soluções Digitais LTDA. Todos os direitos reservados.
