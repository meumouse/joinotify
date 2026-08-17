Versão 2.3.0
* Novo recurso: conexão do WhatsApp pela API oficial do Joinotify, substituindo o formato de Proxy API sobre a Evolution API
     - Botão "Conectar ao Joinotify" nas configurações: a conta é autorizada no painel e a chave da API é entregue ao site sem que você precise copiar e colar nada (colar a chave manualmente continua disponível)
     - Números conectados no painel são importados para o site, com nome verificado, identificador do número, qualidade atribuída pela Meta e limite de conversas iniciadas em 24 horas
     - Suporte a múltiplos números e múltiplas contas empresariais (WABA) na mesma conta: cada ação escolhe de qual número sai a mensagem
     - Quem ainda usa o formato antigo continua funcionando normalmente e passa a ver um aviso de descontinuação
* Novo recurso: templates de mensagem aprovados pela Meta
     - Os templates da sua conta são listados dentro do construtor, com prévia do conteúdo, categoria, idioma e situação da aprovação
     - Nova ação "WhatsApp: Mensagem de template", com uma variável do Joinotify para cada variável do template
     - Botão para sincronizar os templates quando algum for criado direto no Business Manager
     - Aviso quando o template escolhido está pausado, desabilitado ou reprovado pela Meta
     - Templates são a única forma de falar com alguém fora da janela de 24 horas, que é a situação da maioria dos fluxos automáticos
* Novos tipos de mensagem permitidos pela API oficial: botões de resposta, lista de opções, botão de link, localização, cartão de contato, figurinha e reação
* Novo recurso: confirmação real de entrega
     - O site passa a receber os eventos da conta e registrar quando a mensagem foi entregue, lida ou recusada, em vez de apenas "aceita pela API"
     - Falhas de entrega passam a aparecer no log de depuração com o motivo informado pela Meta
     - Mensagens recebidas do contato abrem a janela de 24 horas, permitindo responder com texto livre
* Melhoria: código de acesso do login por OTP passa a ser entregue por template de autenticação na API oficial, com nome e idioma configuráveis
* Melhoria: quando a API pede para aguardar (limite de requisições), o reenvio respeita exatamente o tempo informado
* Recurso descontinuado: "Ativar Proxy API", em Configurações → Geral, passa a ser marcado como depreciado e será removido em uma próxima versão
     - As rotas continuam funcionando por enquanto, mas o envio passa a ser exclusivamente pela API do Joinotify (API oficial do WhatsApp)
     - As configurações do proxy agora exibem um aviso de descontinuação
     - As respostas das rotas do proxy passam a informar a descontinuação nos cabeçalhos "Deprecation" e "X-Joinotify-Deprecation", e cada chamada é registrada no log de depuração para ajudar a identificar integrações que ainda usam o formato antigo
* Observação: a API oficial não oferece envio para grupos; nesse modo as ações de grupo ficam indisponíveis e avisam o motivo
* Melhoria: o assistente de configuração passa a ser exibido em tela cheia, sobre o painel do WordPress
     - A barra e o menu do administrador ficam cobertos enquanto o assistente está aberto, para que o fluxo não seja interrompido no meio
     - A página por trás não rola mais junto, e o esqueleto de carregamento já aparece em tela cheia, sem o salto que existia ao montar a tela
     - Novo botão de fechar no canto superior direito, disponível em todas as etapas
* O envio de dados de uso, que existia desde a 2.4.0 sem destino, passa a funcionar de verdade
     - Continua **desligado por padrão**: nada sai do site antes de você aceitar no assistente
     - O site é identificado por um valor aleatório gerado aqui mesmo — nunca derivado do endereço — mais a chave da API que você já usa para enviar mensagens
     - O identificador aparece em Configurações → Sobre, para você citá-lo num chamado de suporte; sem ele, o suporte não tem como achar o seu site
     - Os eventos são acumulados e enviados em lote por tarefa agendada, no máximo a cada poucas horas, nunca durante o carregamento de uma página
     - Um evento repetido no mesmo dia conta uma vez só: uma loja com cinco mil pedidos diários gera cerca de dez eventos, não cinco mil
     - Só sai o que está no catálogo, e cada propriedade tem tipo fechado — não existe campo de texto livre, então conteúdo de mensagem não cabe no formato
     - Códigos de erro são normalizados antes de sair; o detalhe fica num identificador do ponto do código que gerou a falha
     - Desligar apaga o que ainda estava na fila e avisa o servidor para parar de contar esta instalação (o filtro `Joinotify/Telemetry/Send_Opt_Out` suprime esse último aviso)
     - O assistente passa a mostrar também exemplos dos eventos e como o site é identificado
* Melhoria: a biblioteca de modelos de fluxo passa a ser servida pela API do Joinotify, no mesmo endereço da conta (`api.joinotify.com`), em vez do endereço dedicado que existia antes
     - Ao usar um modelo, o download é contabilizado na biblioteca, o que passa a alimentar a contagem de instalações de cada modelo
     - Quando um modelo é revisado, o site percebe pela assinatura publicada no catálogo e busca a versão nova sozinho, sem precisar limpar cache
     - Falhas ao carregar a biblioteca passam a aparecer no log de depuração com o motivo informado pela API
* Correção: o plugin não removia nenhuma das próprias tarefas agendadas ao ser desativado
     - Um site que desativava o plugin seguia com os agendamentos na base até alguém limpar à mão
     - A desativação agora limpa todas elas. Nada é apagado além disso — desativar não é desinstalar
* Novos ganchos para extensões
     - `Joinotify/Settings/Saved`, com os valores antes e depois de cada salvamento
     - `Joinotify/Sender_Selected`, quando o site passa a ter um número de origem (leva só como foi escolhido, nunca o número)
     - `Joinotify/Notification_Queue/Item_Retried`, quando uma mensagem falha e volta para a fila
     - `Joinotify/Debug_Log/Recorded`, disparado em erros mesmo com o registro de logs desligado
* O plugin passa a ser 100% gratuito e software livre, sob a licença GNU GPL v2 ou posterior
     - Todos os recursos estão liberados: não existe mais versão paga, período de teste nem verificação de licença
     - A tela "Licença" e o sistema de licenciamento foram removidos; a chave da API do Joinotify passa a ser a única credencial
     - Ao atualizar, a chave de licença, o status e os dados do servidor de licenciamento são apagados do banco automaticamente
* Novo recurso: assistente de configuração em 6 etapas, exibido logo após a ativação
     - País padrão, já pré-selecionado a partir do endereço da loja WooCommerce, do idioma do site ou do fuso horário
     - Conexão com o Joinotify: a chave da API é validada na hora e os números da conta são importados
     - Provedor de IA (opcional), com a chave do provedor
     - Documentação do plugin, em https://docs.joinotify.com
     - Autorização para o envio de dados de uso anônimos
     - Ao final, criar a primeira automação ou ir para as configurações
     - Instalações antigas que nunca passaram pelo assistente também o veem, uma única vez, ao abrir uma tela do Joinotify
* Novo recurso: envio de dados de uso anônimos, desligado por padrão
     - O assistente mostra exatamente o conteúdo que seria enviado antes de você decidir
     - Nunca inclui endereço do site, e-mail, telefones, contatos, conteúdo de mensagens, fluxos ou credenciais
     - Pode ser desligado a qualquer momento em Configurações → Sobre
* Novo recurso: modal de configuração no card do WhatsApp, em Configurações → Integrações
     - Botão "Configurar" abre o modal com a chave da API do Joinotify, o transporte de mensagens, o ID do número e o ID da conta empresarial
     - O botão "Conectar" valida a chave na hora contra a API e importa os números da conta, preenchendo o ID do número e o da conta empresarial automaticamente
     - Se a chave for recusada, a chave anterior é restaurada — o site nunca fica com uma chave que acabou de falhar
     - Botão "Desconectar" apaga a chave sem remover os números já importados
     - Com uma chave salva, o campo exibe o prefixo público dela (o restante fica mascarado) e um botão "Remover chave", com confirmação
     - A chave informada no assistente de configuração aparece já salva no campo, e salvar as configurações não a apaga
     - A chave da API não é enviada ao navegador em nenhuma tela: as configurações, o construtor de automações e a resposta de salvar recebem apenas o prefixo público e a informação de que existe uma chave salva
     - A chave da API do WhatsApp, o token do Telegram e a chave do Resend passam a ser excluídos da exportação de configurações
* Alteração: as atualizações passam a ser entregues pelo próprio WordPress
     - O verificador de atualizações próprio do plugin e as opções "Atualizações automáticas" e "Avisos de atualização" foram removidos
     - Sites instalados fora do diretório do WordPress.org precisam reinstalar o plugin pelo diretório para voltar a receber atualizações
* Alteração: a Proxy API (descontinuada) passa a vir desligada em instalações novas
* Recurso removido: instalador de extensões que baixava pacotes de um endereço externo
* Alteração: o construtor deixa de oferecer ações de integrações desativadas
     - Telegram, Resend, WhatsApp e o cupom de desconto do WooCommerce só aparecem na biblioteca de ações quando a integração correspondente está ativa em Configurações → Integrações
     - Antes, essas ações apareciam mesmo desligadas e a automação não entregava nada ao ser executada
     - Automações já salvas continuam abrindo e exibindo essas etapas normalmente, mesmo com a integração desligada depois
* Alteração: todas as ações de WhatsApp do construtor passam a exibir a logo do WhatsApp
     - Modelo aprovado, mensagem com IA, botões de resposta, lista de opções, botão de link, localização, contato, figurinha e reação usavam ícones genéricos
* Adequação às diretrizes do diretório de plugins do WordPress.org: readme.txt com a declaração de todos os serviços externos utilizados, licença GPL e o código-fonte do frontend Vue distribuído junto do pacote
* Recurso removido: ação "Snippet PHP" do construtor
     - A ação executava código PHP arbitrário com `eval()`, o que o diretório do WordPress.org não permite
     - A geração do trecho por IA, que existia só para essa ação, também foi removida
     - **Atenção:** automações salvas que usam essa etapa continuam abrindo, mas o passo do snippet deixa de ser executado. Quem depende dele deve mover a lógica para um plugin próprio, ligado aos ganchos `Joinotify/...`
* Recurso removido: botão que instalava o addon de recuperação de carrinho a partir de um endereço externo
     - O botão já não funcionava (não havia mais código que respondesse ao clique) e o endereço remoto contrariava as diretrizes do diretório
     - No lugar dele, o construtor agora apenas informa qual plugin precisa ser instalado e ativado
* Correções de conformidade apontadas pelo Plugin Check: consultas ao banco preparadas e documentadas, escape de saída no ícone das integrações, comentários `translators:` e placeholders numerados em todas as mensagens traduzíveis, `date()` trocado por `wp_date()`/`gmdate()`, `mt_rand()` por `wp_rand()`, `strip_tags()` por `wp_strip_all_tags()`, e as chamadas de depuração (`error_log`/`print_r`) redirecionadas para o log do próprio plugin
* Correção: o gerador do arquivo de tradução (`.pot`) lia a pasta de histórico do editor e trazia de volta textos já removidos do código; agora ele também leva os comentários `translators:` para o arquivo, que antes se perdiam
* Correção: valores monetários chegavam quebrados na mensagem
     - `{{ wc_currency_symbol }}` enviava o código interno do WooCommerce em vez do símbolo: o real aparecia como `&#82;&#36;` no lugar de `R$`
     - Os totais eram enviados com o número cru do pedido (`68.70`), sem o separador decimal do idioma da loja e diferente do que a prévia do construtor mostrava
     - `{{ joinotify_coupon_discount_formatted }}` enviava o HTML inteiro do preço, com as tags `<span>` visíveis na mensagem
     - `{{ fcrc_cart_total }}` (carrinho abandonado do Flexify Checkout) enviava o valor sem formatação alguma
     - **Atenção:** `{{ wc_order_total }}`, `{{ wc_total_discount }}`, `{{ wc_total_tax }}` e `{{ wc_total_refunded }}` agora já incluem o símbolo da moeda (`R$ 68,70`), como a prévia do construtor sempre mostrou. Fluxos que escreviam `{{ wc_currency_symbol }}{{ wc_order_total }}` precisam remover o `{{ wc_currency_symbol }}`, senão o símbolo aparece duas vezes

Versão 2.2.0 (03/08/2026)
* Novo recurso: Ação "Loop" no construtor de fluxos, que percorre uma coleção e executa as ações do corpo uma vez para cada item, permitindo enviar várias mensagens em sequência
     - Coleções disponíveis: arquivos digitais do pedido, itens comprados do pedido e lista a partir de uma variável (separada por linha ou delimitador)
     - Entrega um arquivo por mensagem: por exemplo, uma mensagem de mídia para cada arquivo de download vinculado ao pedido
     - Nova fonte de anexo "Arquivo do item do loop", que envia o arquivo da iteração atual sem consumir o limite de downloads do cliente
     - Suporte a tempo de espera (delay) entre as iterações
* Novas variáveis do loop, disponíveis dentro do corpo do loop: {{ loop_value }}, {{ loop_index }}, {{ loop_number }}, {{ loop_count }}, {{ loop_file_name }}, {{ loop_download_url }}, {{ loop_product_name }}, {{ loop_item_name }} e {{ loop_item_quantity }}
     - As variáveis do loop são processadas em qualquer campo onde forem inseridas (legenda, URL de mídia, URL de anexo e destinatário)
* Recurso removido: Notificar quando o WhatsApp desconectar
* Correção de bugs
     - O código do país padrão (DDI) não era aplicado quando o número não incluía o código, fazendo o envio falhar; agora o país de fallback configurado é usado corretamente

Versão 2.1.0 (27/07/2026)
* Novo recurso: Entrega de produtos digitais do WooCommerce diretamente pelo construtor de fluxos, enviando arquivos e PDFs por e-mail ou WhatsApp
* Novo recurso: Envio de anexos nas ações de e-mail (Resend) e de mídia do WhatsApp, com campo reutilizável que aceita arquivos da biblioteca de mídia, links ou os arquivos digitais do próprio pedido
     - Anexos grandes no e-mail são substituídos automaticamente pelo link de download, garantindo o envio da mensagem
     - Envio de múltiplos arquivos na ação de mídia do WhatsApp
* Novo acionamento: "Acesso a produto digital liberado" (WooCommerce), recomendado para fluxos de entrega, pois os links de download já existem nesse momento
* Novo acionamento: "Arquivo digital baixado" (WooCommerce), disparado quando o cliente baixa um arquivo, ambos com filtro opcional por produto
* Novas variáveis do WooCommerce para produtos digitais: lista de produtos para download, nomes e links dos arquivos, links sem o nome, data de expiração, downloads restantes, link da área de downloads na conta do cliente e link de download por produto específico
* Melhoria: variáveis passam a respeitar o acionamento escolhido também no envio, evitando que variáveis sem contexto sejam preenchidas indevidamente
* Melhoria: preparação para o novo servidor de licenças, com migração automática e sem intervenção do usuário
     - O plugin passa a usar o novo servidor assim que o atual deixar de responder, mantendo a mesma chave de licença
     - Sites já ativados são migrados em segundo plano, sem travar o painel
     - Nenhuma licença é desativada quando o servidor está fora do ar ou quando há divergência de cadastro; nesses casos o plugin continua funcionando e avisa na tela de licença
     - Atualizações do plugin passam a ser entregues pelo novo servidor após a migração
* Correção de bugs
     - Ordem dos argumentos no acionamento de reembolso parcial do WooCommerce, que fazia a variável do pedido receber o identificador do reembolso
     - Licença expirada ou recusada pelo servidor continuava liberando os recursos premium por até 24 horas
     - Abrir a tela de licença podia desativar a licença do site quando a data de validade já havia passado
     - Licenças vitalícias marcadas como "Unlimited" ou "Lifetime" eram tratadas como expiradas
     - Resposta de ativação da licença podia ser reaproveitada na desativação, por compartilharem o mesmo cache

Versão 2.0.0 (02/07/2026)
* Novo construtor de fluxos: interface totalmente redesenhada em formato de canvas visual, com arrastar e soltar, conectar etapas, zoom, ajuste automático à tela e botões de desfazer/refazer
* Novo recurso: Inteligência Artificial no construtor de fluxos
     - Criação de fluxos completos automaticamente a partir de uma descrição em texto
     - Geração de mensagens dinâmicas do WhatsApp com IA
     - Geração de variáveis inteligentes com IA
     - Criação de snippets PHP com auxílio da IA
* Novo recurso: Variáveis de texto personalizadas, criadas pelo próprio usuário a partir de tipos de conteúdo e campos do site (inclui suporte a pedidos do WooCommerce)
* Novo recurso: Histórico de mensagens enviadas, com filtros e seletor de data por mês e ano
* Novo recurso: Login sem senha por código (OTP) enviado via WhatsApp, com suporte a novos canais no futuro
* Novo recurso: Exportar e importar todas as configurações do plugin em arquivo JSON
* Novo recurso: Acionamento "Solicitação de redefinição de senha", com variável do link de redefinição
* Novo editor de mensagens com formatação visual (negrito, itálico, emojis) convertida automaticamente para o padrão do WhatsApp
* Melhoria: pré-visualização de mídia diretamente na etapa de mensagem de mídia do WhatsApp
* Melhoria: variáveis de texto destacadas e clicáveis dentro dos campos, com aviso quando indisponíveis no acionamento escolhido
* Melhoria: ações sinalizam quando há configurações obrigatórias pendentes
* Melhoria: catálogo de condições mais completo, com seleção por lista e seletor de produtos nos valores
* Melhoria: agendamento de mensagens com data e hora específicas (tempo de espera), com fila e reprocessamento de notificações que falharam
* Melhoria: aba "Integrações" renomeada para "Aplicativos"
* Melhoria: verificação manual de atualização na aba "Sobre"
* Melhoria: migração automática dos fluxos das versões anteriores ao atualizar o plugin
* Otimizações de desempenho no carregamento do plugin e nas telas administrativas
* Suporte aprimorado a traduções (português, inglês e espanhol)
* Novos idiomas adicionados: Francês, Italiano, Alemão e Português de Portugal

Versão 1.4.7 (12/04/2026)
* Recurso adicionado: Fila para processamento de mensagens
* Mudança de tecnologia do frontend para Vue.js, Vite e Tailwind CSS

Versão 1.4.6 (10/02/2026)
* Otimizações
* Correção de bugs
     - Erro fatal devido a falta da classe ElementorPro\Modules\Forms\Classes\Action_Base

Versão 1.4.5 (24/01/2026)
* Correção de bugs
     - Erro fatal ao carregar página "Todos os fluxos": Call to undefined function convert_to_screen()
* Otimizações
* Recurso adicionado: WooCommerce -> Formato do endereço completo (faturamento e entrega)

Versão 1.4.4 (12/12/2025)
* Otimizações
     - Melhorias em segurança no instanciamento de classes

Versão 1.4.3 (08/12/2025)
* Correção de bugs
     - Verificar se o pedido foi pago
     - Criptografia de emojis em mensagens
     - Contagem de posts na tabela Todos os fluxos

Versão 1.4.2 (27/11/2025)
* Correção de bugs
     - Erro na validação de string com Proxy API
* Otimizações

Versão 1.4.1 (28/10/2025)
* Alteração na API de consulta de atualizações

Versão 1.4.0 (29/08/2025)
* Otimizações
* Recurso adicionado: Legenda para mensagens de mídia do WhatsApp
* Recurso adicionado Variáveis de texto {{ post_title }}, {{ post_date }}, {{ post_content }}, {{ post_link }}, {{ post_tags }}, {{ post_categories }} e {{ post_featured_image }}

Versão 1.3.7 (13/08/2025)
* Correção de bugs
     - Incapacidade de editar fluxos com plugin Academy LMS e similares

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
