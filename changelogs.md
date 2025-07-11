Versão 1.3.6 (11/07/2025)
* Correção de bugs
     - Prioridade e argumentos da função add_action() na classe Woo_Subscriptions informados fora do array de callback
     - Falha na verificação de status de pagamento de pedidos

Versão 1.3.5 (09/07/2025)
* Correção de bugs
     - Erro fatal ao alterar status de pedido: Uncaught Error: Class name must be a valid object or a string in /woocommerce/src/Internal/DataStores/Orders/OrdersTableDataStore.php:1524
* Recurso adicionado: Validação de status do post no acionamento "Post tem status alterado"

Versão 1.3.4 (16/06/2025)
* Correção de bugs
     - Link de recuperação do carrinho é vazio (Flexify Checkout - Recuperação de carrinhos abandonados)
* Otimizações
     - Melhorias na responsividade em desktop
* Recurso adicionado: Mostrar notificações de atualização de versão

Versão 1.3.3 (10/06/2025)
* Recurso adicionado: Receber avisos quando WhatsApp estiver desconectado
* Recurso removido: Ao entrar na etapa 1 da integração Flexify Checkout
* Recurso removido: Ao entrar na etapa 2 da integração Flexify Checkout
* Recurso removido: Ao entrar na etapa 3 da integração Flexify Checkout
* Recurso adicionado: Variáveis de texto: {{ fcrc_first_name }}, {{ fcrc_last_name }}, {{ fcrc_phone }}, {{ fcrc_email }}, {{ fcrc_cart_total }} (Flexify Checkout - Recuperação de carrinhos abandonados)
* Recurso adicionado: Acionamento: Coleta de lead via modal (Flexify Checkout - Recuperação de carrinhos abandonados)
* Recurso adicionado: Acionamento: Coleta de lead via checkout (Flexify Checkout - Recuperação de carrinhos abandonados)

Versão 1.3.2 (29/05/2025)
* Correção de bugs
     - Método set_default_options() indefinido na classe Helpers na linha 171
* Otimizações
     - Preencher o remetente ao importar um fluxo

Versão 1.3.1 (26/05/2025)
* Correção de bugs
     - Ação de Tempo de espera

Versão 1.3.0 (08/05/2025)
* Correção de bugs
* Otimizações
* Correção de segurança se remetente está registrado no site
* Mudança na API de envio de mensagens via WhatsApp

Versão 1.2.5 (24/03/2025)
* Correção de bugs
     - Correção na chamada de ganchos da integração Woo Subscriptions
* Otimizações

Versão 1.2.2 (17/03/2025)
* Correção de bugs:
     - Variáveis de texto em acionamentos para WooCommerce em modo testes não estavam sendo substituídas corretamente.
* Otimizações
* Recurso modificado: Variáveis de texto {{ wc_order_total }}, {{ wc_total_discount }}, {{ wc_total_tax }}, {{ wc_total_refunded }}, agora retornam valores com símbolo de moeda formatados.
* Recurso removido: Condição "Status do pedido" no acionamento "Novo pedido"
* Recurso adicionado: Adição de ações entre ações existentes no fluxo
* Recurso adicionado: Formatação de textos com variáveis do WhatsApp
* Recurso adicionado: Tradução para o idioma inglês (en-US)
* Recurso adicionado: Tradução para o idioma espanhol (es-ES)

Versão 1.2.0 (12/03/2025)
* Correção de bugs
* Otimizações
* Recurso adicionado: Biblioteca "giggsey/libphonenumber-for-php" para formatação e validação de telefones em formato internacional
* Recurso adicionado: Biblioteca "Selectize" para multi seleção de elementos
* Recurso removido: Variável de texto {{ post_id }}
* Recurso adicionado: Condições "Método de pagamento", "Método de entrega" e "Pedido pago"
* Recurso adicionado: Acionamentos: "Pagamento processado pelo PayPal"
* Recurso adicionado: Classe "Routines" para execução de rotinas; E adicionado rotina de verificação de conexão do telefones e atualizações
* Recurso adicionado: Variáveis de texto {{ fc_inter_pix_copia_cola }}, {{ fc_inter_pix_expiration_time }}, {{ fc_inter_bank_slip_url }} e {{ fcrc_recovery_link }}

Versão 1.1.2 (24/02/2025)
* Correção de bugs

Versão 1.1.1 (24/02/2025)
* Correção de bugs

Versão 1.1.0 (24/02/2025)
* Correção de bugs
* Otimizações
* Recurso adicionado: Ativar modo depuração
* Recurso adicionado: Integração com formulários do Elementor
* Recurso removido: Variáveis de texto {{ br }} e {{ phone }}
* Recurso removido: Atualização de configurações automáticas
* Recurso adicionado: Variáveis de texto {{ wc_billing_first_name }}, {{ wc_billing_last_name }}, {{ wc_billing_email }}, {{ wc_billing_phone }}, {{ wc_shipping_phone }}, {{ wc_order_status }}, {{ wc_billing_full_address }}, {{ wc_shipping_full_address }}, {{ wc_order_total }}, {{ wc_total_discount }}, {{ wc_total_tax }}, {{ wc_total_refunded }}, {{ wc_coupon_codes }}, {{ wc_payment_method_title }}, {{ wc_shipping_address }}, {{ wc_checkout_field=[FIELD_ID] }}
* Recurso adicionado: Ativar atualizações automáticas
* Recurso adicionado: Ação "Snippet PHP" no construtor de fluxos
* Recurso adicionado: Ação "Cupom de desconto " no construtor de fluxos para integração com WooCommerce
* Recurso adicionado: Obter informações de grupos do WhatsApp
* Recurso modificado: Alteração da biblioteca de emojis (Picmo -> EmojioneArea)

Versão 1.0.5 (05/12/2024)
* Correção de compatibilidade com PHP 7.4

Versão 1.0.4 (22/11/2024)
* Correção de bugs

Versão 1.0.3 (22/11/2024)
* Correção de bugs

Versão 1.0.2 (22/11/2024)
* Correção de bugs

Versão 1.0.1 (21/11/2024)
* Correção de bugs

Versão 1.0.0 (20/11/2024)
* Versão inicial
