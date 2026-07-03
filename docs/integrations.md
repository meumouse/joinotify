# Contrato declarativo de integracoes do Joinotify

Este documento descreve o contrato para adicionar novas integracoes na aba **Settings > Integrations**, com foco em reuso de componentes do frontend, configuracao do modal, HTML personalizado e defaults automaticos.

## Objetivo

O fluxo de integracao foi desenhado para que um desenvolvedor consiga:

- registrar um novo card de integracao via filtro `Joinotify/Settings/Tabs/Integrations`
- declarar os campos que serao exibidos no modal de configuracao
- inserir HTML personalizado ou componentes Vue dentro do modal
- reutilizar componentes nativos do frontend quando possivel
- fornecer valores padrao para `Joinotify/Admin/Set_Default_Options`
- manter compatibilidade com integracoes antigas que ainda usam `fields` e `comming_soon`

## Estrutura basica de uma integracao

O item retornado no filtro pode seguir esta estrutura:

```php
$integrations['minha_integracao'] = array(
    'title' => esc_html__( 'Minha Integracao', 'joinotify-minha-integracao' ),
    'description' => esc_html__( 'Descricao curta da integracao.', 'joinotify-minha-integracao' ),
    'icon' => '<svg>...</svg>',
    'category' => 'channels',
    'setting_key' => 'enable_minha_integracao',
    'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Minha_Integracao',
    'settings' => array(
        array(
            'type' => 'toggle',
            'key' => 'enable_minha_integracao',
            'label' => esc_html__( 'Ativar integracao', 'joinotify-minha-integracao' ),
            'description' => esc_html__( 'Liga ou desliga a integracao.', 'joinotify-minha-integracao' ),
            'default' => 'no',
        ),
        array(
            'type' => 'text',
            'key' => 'api_key',
            'label' => esc_html__( 'API Key', 'joinotify-minha-integracao' ),
            'description' => esc_html__( 'Chave de acesso fornecida pelo servico.', 'joinotify-minha-integracao' ),
            'default' => '',
        ),
    ),
    'defaults' => array(
        'enable_minha_integracao' => 'no',
        'api_key' => '',
    ),
    'modal' => array(
        'title' => esc_html__( 'Configuracoes da integracao', 'joinotify-minha-integracao' ),
        'description' => esc_html__( 'Ajuste as credenciais e opcoes da integracao.', 'joinotify-minha-integracao' ),
        'button_label' => esc_html__( 'Configurar', 'joinotify-minha-integracao' ),
        'blocks' => array(
            MeuMouse\Joinotify\Integrations\Integrations_Base::modal_html_block(
                '<div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Conteudo personalizado do modal.</div>'
            ),
        ),
    ),
);
```

## Categorias (aba Aplicativos)

A aba **Settings > Applications** agrupa os cards em secoes por categoria. Cada integracao declara a categoria a que pertence pela chave `category`:

```php
'category' => 'channels',
```

Categorias nativas (id => rotulo):

- `channels` — Communication channels (WhatsApp, Telegram, Resend)
- `ai` — Artificial Intelligence (OpenAI, Anthropic)
- `ecommerce` — E-commerce (WooCommerce, Flexify Checkout)
- `forms` — Forms & page builders (WPForms, Elementor)
- `content` — Content & CMS (WordPress)
- `security` — Authentication (OTP Login)
- `developer` — Advanced
- `others` — fallback usado quando `category` esta ausente ou nao registrada

Se `category` for omitida, o card cai automaticamente em `others`. Secoes sem nenhum card sao ocultadas, e categorias declaradas nos cards mas nao registradas no catalogo aparecem por ultimo (com rotulo derivado do id).

### Registrar uma categoria propria

O catalogo e exposto pelo filtro `Joinotify/Settings/Integrations/Categories`, no mesmo padrao das categorias de acoes do builder. Cada item aceita `id`, `label`, `icon` (SVG) e `priority` (ordena as secoes):

```php
add_filter( 'Joinotify/Settings/Integrations/Categories', function( $categories ) {
    $categories[] = array(
        'id' => 'crm',
        'label' => esc_html__( 'CRM', 'joinotify-minha-integracao' ),
        'icon' => '<svg viewBox="0 0 24 24" fill="currentColor">...</svg>',
        'priority' => 35,
    );

    return $categories;
} );
```

Depois basta os cards apontarem `'category' => 'crm'`.

## Tipos de campos suportados

Os campos declarativos podem usar os tipos nativos abaixo:

- `toggle`
- `text`
- `textarea`
- `select`
- `phone`
- `color`
- `color-scale`
- `input-group`

O sistema tambem aceita componentes customizados atraves do campo `component`.

### `input-group`

O tipo `input-group` serve para agrupar controles relacionados no mesmo bloco visual. Ele foi pensado para casos como:

- `input + select`
- `input + select + botao`
- `input + botao`
- `input + texto auxiliar`

No backend, o grupo deve ser declarado com `Integrations_Base::field_input_group()`. Cada item do grupo pode ser criado com os helpers:

- `input_group_text_item()`
- `input_group_select_item()`
- `input_group_button_item()`
- `input_group_addon_item()`

Exemplo:

```php
MeuMouse\Joinotify\Integrations\Integrations_Base::field_input_group(
    'discount_rule',
    esc_html__( 'Regra de desconto', 'joinotify-minha-integracao' ),
    esc_html__( 'Escolha o tipo e informe o valor em um unico grupo.', 'joinotify-minha-integracao' ),
    array(
        MeuMouse\Joinotify\Integrations\Integrations_Base::input_group_select_item(
            esc_html__( 'Tipo', 'joinotify-minha-integracao' ),
            array(
                array( 'value' => 'fixed', 'label' => esc_html__( 'Fixo', 'joinotify-minha-integracao' ) ),
                array( 'value' => 'percent', 'label' => esc_html__( 'Percentual', 'joinotify-minha-integracao' ) ),
            ),
            array(
                'key' => 'discount_type',
                'default' => 'fixed',
            )
        ),
        MeuMouse\Joinotify\Integrations\Integrations_Base::input_group_text_item(
            esc_html__( 'Valor', 'joinotify-minha-integracao' ),
            array(
                'key' => 'discount_value',
                'placeholder' => '10',
                'inputmode' => 'numeric',
                'default' => '',
            )
        ),
        MeuMouse\Joinotify\Integrations\Integrations_Base::input_group_button_item(
            esc_html__( 'Aplicar', 'joinotify-minha-integracao' ),
            array(
                'action' => 'copy',
                'source' => 'discount_value',
            )
        ),
    )
);
```

### Exemplo de campo com componente customizado

```php
array(
    'type' => 'component',
    'component' => 'otp',
    'key' => 'otp_code',
    'label' => esc_html__( 'Codigo OTP', 'joinotify-minha-integracao' ),
    'description' => esc_html__( 'Componente customizado de OTP.', 'joinotify-minha-integracao' ),
    'default' => '',
),
```

## Como o modal de integracao e renderizado

O modal de integracao consome o conteudo nesta ordem:

1. `modal.blocks` quando existir
2. `modal.content` ou `modal.html` como fallback de HTML direto
3. `settings`
4. `fields` legado

Os blocos do modal aceitam dois formatos principais:

- `type => 'html'` para HTML confiavel renderizado no modal
- `type => 'component'` para um componente Vue registrado no frontend
- `type => 'component'` continua sendo a forma de usar componentes customizados

O `input-group` nao usa `type => 'component'`. Ele e um tipo nativo declarativo, com suporte a um conjunto de itens dentro do mesmo campo.

O HTML e sanitizado no backend com `wp_kses_post()`. Use esse recurso para conteudo estatico, avisos, caixas de ajuda e estrutura adicional dentro do modal.

## Defaults e persistencia

Os valores iniciais devem ser declarados em `defaults`. Eles sao usados para:

- preencher `Joinotify/Admin/Set_Default_Options`
- manter o estado da integracao consistente
- evitar defaults espalhados em varios pontos do codigo

Se a integracao informar `setting_key` e nao declarar o valor padrao dele, o sistema assume `no` como fallback quando fizer sentido para ativacao/desativacao.

## Reuso de componentes no frontend

O frontend ja oferece componentes prontos para o modal de configuracao. A recomendacao e reutiliza-los sempre que possivel.

### Componentes nativos

- `toggle`
- `text`
- `textarea`
- `select`
- `phone`
- `color`
- `color-scale`

### Componentes customizados

Para um componente customizado ser renderizado no modal, ele deve ser registrado no registry global:

```js
window.JoinotifyFieldComponents = {
  'input-group': InputGroupComponent,
  'otp': OtpComponent,
};
```

Depois disso, o campo pode declarar:

```php
array(
    'type' => 'component',
    'component' => 'otp',
    'key' => 'otp_code',
    'label' => esc_html__( 'Codigo OTP', 'joinotify-minha-integracao' ),
    'description' => esc_html__( 'Componente customizado de OTP.', 'joinotify-minha-integracao' ),
    'default' => '',
),
```

### Blocos de HTML no modal

Se voce precisar apenas inserir conteudo estatico, use um bloco HTML:

```php
'modal' => array(
    'title' => esc_html__( 'Configuracoes do exemplo', 'joinotify-exemplo' ),
    'blocks' => array(
        MeuMouse\Joinotify\Integrations\Integrations_Base::modal_html_block(
            '<div class="rounded-lg border border-primary-100 bg-primary-50 p-4">Instrucoes ou conteudo personalizado.</div>'
        ),
    ),
),
```

Se voce quiser renderizar um componente Vue no modal:

```php
'modal' => array(
    'blocks' => array(
        MeuMouse\Joinotify\Integrations\Integrations_Base::modal_component_block(
            'otp',
            array(
                'placeholder' => esc_html__( 'Selecione um template', 'joinotify-exemplo' ),
            )
        ),
    ),
),
```

## Compatibilidade com integracoes antigas

O contrato atual preserva compatibilidade com:

- `fields` legado
- `comming_soon` com erro de escrita
- `action_hook` existente
- `modal.content` e `modal.html` como fallback de conteudo

Isso permite migrar integracoes antigas aos poucos, sem quebrar cards ja publicados.

## Fluxo recomendado

### 1. Registrar o card

Adicione a integracao no filtro `Joinotify/Settings/Tabs/Integrations`.

### 2. Declarar os campos

Prefira `settings` com o array de campos que sera renderizado no modal.

### 3. Inserir conteudo complementar no modal

Use `modal.blocks` para instrucoes, avisos, previews ou componentes customizados.

### 4. Definir defaults

Inclua os valores iniciais em `defaults` para manter consistencia com o reset e com o bootstrap.

### 5. Reutilizar componentes do frontend

Quando o campo puder ser atendido por componente existente, use o tipo padrao.

### 6. Usar componente customizado apenas quando necessario

Se a UI exigir comportamento especifico, registre o componente no frontend e referencie-o por `component`.

## Exemplo pratico: OTP Login

Exemplo de integracao com ativacao, chave de API e conteudo extra no modal:

```php
$integrations['otp_login'] = array(
    'title' => esc_html__( 'OTP Login - Passwordless authentication', 'joinotify-otp-login' ),
    'description' => esc_html__( 'Envia codigo de verificacao por WhatsApp para login sem senha.', 'joinotify-otp-login' ),
    'icon' => '<svg>...</svg>',
    'setting_key' => 'enable_otp_login_integration',
    'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Otp_Login',
    'settings' => array(
        array(
            'type' => 'toggle',
            'key' => 'enable_otp_login_integration',
            'label' => esc_html__( 'Ativar integracao', 'joinotify-otp-login' ),
            'description' => esc_html__( 'Habilita o envio de OTP via WhatsApp.', 'joinotify-otp-login' ),
            'default' => 'no',
        ),
        array(
            'type' => 'text',
            'key' => 'otp_sender_name',
            'label' => esc_html__( 'Nome do remetente', 'joinotify-otp-login' ),
            'description' => esc_html__( 'Nome exibido na mensagem enviada ao usuario.', 'joinotify-otp-login' ),
            'default' => '',
        ),
        array(
            'type' => 'component',
            'component' => 'otp',
            'key' => 'otp_template',
            'label' => esc_html__( 'Template OTP', 'joinotify-otp-login' ),
            'description' => esc_html__( 'Componente customizado para selecionar o template.', 'joinotify-otp-login' ),
            'default' => '',
            'component_props' => array(
                'placeholder' => esc_html__( 'Selecione um template', 'joinotify-otp-login' ),
            ),
        ),
    ),
    'defaults' => array(
        'enable_otp_login_integration' => 'no',
        'otp_sender_name' => '',
        'otp_template' => '',
    ),
    'modal' => array(
        'title' => esc_html__( 'Configuracoes do OTP Login', 'joinotify-otp-login' ),
        'description' => esc_html__( 'Defina ativacao, remetente e template do OTP.', 'joinotify-otp-login' ),
        'button_label' => esc_html__( 'Abrir configuracoes', 'joinotify-otp-login' ),
        'blocks' => array(
            MeuMouse\Joinotify\Integrations\Integrations_Base::modal_html_block(
                '<div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">Use o template OTP para padronizar a mensagem enviada.</div>'
            ),
        ),
    ),
);
```

## Boas praticas

- Prefira declaracao de `settings` em vez de montar HTML manual no PHP.
- Reutilize componentes nativos antes de criar um componente customizado.
- Mantenha os valores de `key` consistentes com os nomes usados em `defaults`.
- Sempre forneca texto curto e objetivo em `label` e `description`.
- Use `modal` para ajustar a experiencia sem duplicar estrutura em varios pontos.
- Para HTML bruto, mantenha o conteudo simples e confiavel.

## Onde o contrato e consumido

- `admin/src/Integrations/Integrations_Base.php`
- `admin/src/Admin/Default_Options.php`
- `admin/src/Admin/Settings/Registry.php`
- `admin/src/Admin/Settings/Repository.php`
- `app/src/components/fields/fieldRegistry.js`
- `app/src/pages/settings/components/modals/IntegrationSettingsModal.vue`

## Resumo

O modelo declarativo simplifica a inclusao de novas integracoes, reduz duplicacao e agora suporta conteudo personalizado dentro do modal de configuracao. A recomendacao principal e centralizar tudo o que a integracao precisa em `settings`, `defaults` e `modal`, deixando `action_hook` apenas como compatibilidade e transicao.
