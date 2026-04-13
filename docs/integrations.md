# Integracoes declarativas do Joinotify

Este documento descreve o contrato para adicionar novas integracoes na aba **Settings > Integrations**, com foco em reuso de componentes do frontend, configuracao do modal e defaults automaticos.

## Objetivo

O fluxo de integracao foi desenhado para que um desenvolvedor consiga:

- registrar um novo card de integracao via filtro `Joinotify/Settings/Tabs/Integrations`
- declarar os campos que serao exibidos no modal de configuracao
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
    'setting_key' => 'enable_minha_integracao',
    'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Minha_Integracao',
    'settings' => array(
        array(
            'type' => 'toggle',
            'key' => 'enable_minha_integracao',
            'name' => esc_html__( 'Ativar integracao', 'joinotify-minha-integracao' ),
            'description' => esc_html__( 'Liga ou desliga a integracao.', 'joinotify-minha-integracao' ),
            'default' => 'no',
        ),
        array(
            'type' => 'text',
            'key' => 'api_key',
            'name' => esc_html__( 'API Key', 'joinotify-minha-integracao' ),
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
    ),
);
```

## Campos suportados

Os campos declarativos podem usar os tipos padrao abaixo:

- `toggle`
- `text`
- `textarea`
- `select`

O sistema tambem aceita componentes customizados atraves do campo `component`.

### Exemplo de componente customizado

```php
array(
    'type' => 'component',
    'component' => 'input-group',
    'key' => 'sender_name',
    'name' => esc_html__( 'Nome do remetente', 'joinotify-minha-integracao' ),
    'description' => esc_html__( 'Valor exibido no componente personalizado.', 'joinotify-minha-integracao' ),
    'default' => '',
    'component_props' => array(
        'placeholder' => esc_html__( 'Digite o nome', 'joinotify-minha-integracao' ),
        'maxLength' => 60,
    ),
),
```

## Como os campos sao renderizados no modal

O modal de integracao passa a consumir, nesta ordem:

1. `settings`
2. `fields` legado
3. `modal` com titulo, descricao e texto do botao

No frontend, o modal usa o registry de componentes em `window.JoinotifyFieldComponents` para descobrir como renderizar cada tipo.

## Defaults e persistencia

Os valores iniciais devem ser declarados em `defaults`. Eles sao usados para:

- preencher `Joinotify/Admin/Set_Default_Options`
- garantir que a integracao tenha estado inicial consistente
- evitar defaults espalhados em varios pontos do codigo

Se a integracao informar `setting_key` e nao declarar o valor em `defaults`, o sistema assume `no` como fallback quando fizer sentido para ativacao/desativacao.

## Reuso de componentes no frontend

O frontend ja oferece componentes prontos para o modal de configuracao. A recomendacao e reutilizar esses componentes sempre que possivel.

### Componentes nativos

- `toggle`
- `text`
- `textarea`
- `select`

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
    'name' => esc_html__( 'Codigo OTP', 'joinotify-minha-integracao' ),
    'description' => esc_html__( 'Componente customizado de OTP.', 'joinotify-minha-integracao' ),
    'default' => '',
),
```

## Compatibilidade com integracoes antigas

O contrato atual preserva compatibilidade com:

- `fields` legado
- `comming_soon` com erro de escrita
- `action_hook` existente

Isso permite migrar integracoes antigas aos poucos, sem quebrar cards ja publicados.

## Fluxo recomendado

### 1. Registrar o card

Adicione a integracao no filtro `Joinotify/Settings/Tabs/Integrations`.

### 2. Declarar os campos

Prefira `settings` com o array de campos que sera renderizado no modal.

### 3. Definir defaults

Inclua os valores iniciais em `defaults` para manter consistencia com o reset e com o bootstrap.

### 4. Reutilizar componentes do frontend

Quando o campo puder ser atendido por componente existente, use o tipo padrao.

### 5. Usar componente customizado apenas quando necessario

Se a UI exigir comportamento especifico, registre o componente no frontend e referencie-o por `component`.

## Exemplo pratico: OTP Login

Exemplo de integracao com ativacao, chave de API e opcao de personalizacao:

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
            'name' => esc_html__( 'Ativar integracao', 'joinotify-otp-login' ),
            'description' => esc_html__( 'Habilita o envio de OTP via WhatsApp.', 'joinotify-otp-login' ),
            'default' => 'no',
        ),
        array(
            'type' => 'text',
            'key' => 'otp_sender_name',
            'name' => esc_html__( 'Nome do remetente', 'joinotify-otp-login' ),
            'description' => esc_html__( 'Nome exibido na mensagem enviada ao usuario.', 'joinotify-otp-login' ),
            'default' => '',
        ),
        array(
            'type' => 'component',
            'component' => 'otp',
            'key' => 'otp_template',
            'name' => esc_html__( 'Template OTP', 'joinotify-otp-login' ),
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
    ),
);
```

## Boas praticas

- Prefira declaracao de `settings` em vez de montar HTML manual no PHP.
- Reutilize componentes nativos antes de criar um componente customizado.
- Mantenha os `key` dos campos consistentes com os nomes usados em `defaults`.
- Sempre forneca texto curto e objetivo em `name` e `description`.
- Use `modal` para ajustar a experiencia sem duplicar estrutura em varios pontos.

## Onde o contrato e consumido

- `admin/src/Integrations/Integrations_Base.php`
- `admin/src/Admin/Default_Options.php`
- `admin/src/Admin/Settings/Registry.php`
- `admin/src/Admin/Settings/Repository.php`
- `app/src/components/fields/fieldRegistry.js`
- `app/src/pages/settings/components/modals/IntegrationSettingsModal.vue`

## Resumo

O modelo declarativo simplifica a inclusao de novas integracoes, reduz duplicacao e abre espaco para reuso de componentes existentes no frontend. A recomendacao principal e centralizar tudo o que a integracao precisa em `settings`, `defaults` e `modal`, deixando `action_hook` apenas como compatibilidade e transicao.
