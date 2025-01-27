<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Abstract base class for integrations
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
abstract class Integrations_Base {

    /**
     * Add tab items on integration settings tab
     * 
     * @since 1.0.0
     * @return array
     */
    public static function integration_tab_items() {
        return apply_filters( 'Joinotify/Settings/Tabs/Integrations', array(
            'whatsapp' => array(
                'title' => __('WhatsApp', 'joinotify'),
                'description' => __('Use o maior serviço de mensagens do mundo para notificar seu cliente em eventos do seu site.', 'joinotify'),
                'icon' => '<svg viewBox="-2.73 0 1225.016 1225.016" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path fill="#E0E0E0" d="M1041.858 178.02C927.206 63.289 774.753.07 612.325 0 277.617 0 5.232 272.298 5.098 606.991c-.039 106.986 27.915 211.42 81.048 303.476L0 1225.016l321.898-84.406c88.689 48.368 188.547 73.855 290.166 73.896h.258.003c334.654 0 607.08-272.346 607.222-607.023.056-162.208-63.052-314.724-177.689-429.463zm-429.533 933.963h-.197c-90.578-.048-179.402-24.366-256.878-70.339l-18.438-10.93-191.021 50.083 51-186.176-12.013-19.087c-50.525-80.336-77.198-173.175-77.16-268.504.111-278.186 226.507-504.503 504.898-504.503 134.812.056 261.519 52.604 356.814 147.965 95.289 95.36 147.728 222.128 147.688 356.948-.118 278.195-226.522 504.543-504.693 504.543z"></path><linearGradient id="a" gradientUnits="userSpaceOnUse" x1="609.77" y1="1190.114" x2="609.77" y2="21.084"><stop offset="0" stop-color="#20b038"></stop><stop offset="1" stop-color="#60d66a"></stop></linearGradient><path fill="url(#a)" d="M27.875 1190.114l82.211-300.18c-50.719-87.852-77.391-187.523-77.359-289.602.133-319.398 260.078-579.25 579.469-579.25 155.016.07 300.508 60.398 409.898 169.891 109.414 109.492 169.633 255.031 169.57 409.812-.133 319.406-260.094 579.281-579.445 579.281-.023 0 .016 0 0 0h-.258c-96.977-.031-192.266-24.375-276.898-70.5l-307.188 80.548z"></path><image overflow="visible" opacity=".08" width="682" height="639" xlink:href="FCC0802E2AF8A915.png" transform="translate(270.984 291.372)"></image><path fill-rule="evenodd" clip-rule="evenodd" fill="#FFF" d="M462.273 349.294c-11.234-24.977-23.062-25.477-33.75-25.914-8.742-.375-18.75-.352-28.742-.352-10 0-26.25 3.758-39.992 18.766-13.75 15.008-52.5 51.289-52.5 125.078 0 73.797 53.75 145.102 61.242 155.117 7.5 10 103.758 166.266 256.203 226.383 126.695 49.961 152.477 40.023 179.977 37.523s88.734-36.273 101.234-71.297c12.5-35.016 12.5-65.031 8.75-71.305-3.75-6.25-13.75-10-28.75-17.5s-88.734-43.789-102.484-48.789-23.75-7.5-33.75 7.516c-10 15-38.727 48.773-47.477 58.773-8.75 10.023-17.5 11.273-32.5 3.773-15-7.523-63.305-23.344-120.609-74.438-44.586-39.75-74.688-88.844-83.438-103.859-8.75-15-.938-23.125 6.586-30.602 6.734-6.719 15-17.508 22.5-26.266 7.484-8.758 9.984-15.008 14.984-25.008 5-10.016 2.5-18.773-1.25-26.273s-32.898-81.67-46.234-111.326z"></path><path fill="#FFF" d="M1036.898 176.091C923.562 62.677 772.859.185 612.297.114 281.43.114 12.172 269.286 12.039 600.137 12 705.896 39.633 809.13 92.156 900.13L7 1211.067l318.203-83.438c87.672 47.812 186.383 73.008 286.836 73.047h.255.003c330.812 0 600.109-269.219 600.25-600.055.055-160.343-62.328-311.108-175.649-424.53zm-424.601 923.242h-.195c-89.539-.047-177.344-24.086-253.93-69.531l-18.227-10.805-188.828 49.508 50.414-184.039-11.875-18.867c-49.945-79.414-76.312-171.188-76.273-265.422.109-274.992 223.906-498.711 499.102-498.711 133.266.055 258.516 52 352.719 146.266 94.195 94.266 146.031 219.578 145.992 352.852-.118 274.999-223.923 498.749-498.899 498.749z"></path></g></svg>',
                'setting_key' => 'enable_whatsapp_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Whatsapp',
            ),
            'woocommerce' => array(
                'title' => __('WooCommerce', 'joinotify'),
                'description' => __('Envie mensagens para novos pedidos, cancelamentos, reembolsos e recuperação de pedidos não pagos. Mantenha seus clientes atualizados.', 'joinotify'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1010.34 1010.34"><circle cx="505.17" cy="505.17" r="505.17" style="fill:#7f54b3"/><path d="M368.67,374.05a29.76,29.76,0,0,1,22.11-10.94q26.66-1.71,30.77,25.3Q438,498.51,456.88,575.55l77.27-146.79c7.06-13.22,15.72-20.29,26.44-21,15.5-1.14,25.07,8.66,28.94,29.63a669.08,669.08,0,0,0,33.28,120.35q14-134,46.5-194a27,27,0,0,1,23.25-15.5,30.15,30.15,0,0,1,22.11,7.06,27.32,27.32,0,0,1,10.71,20.29A30.39,30.39,0,0,1,722,392.52q-20.51,38.3-34,127.18c-8.66,57.22-12.08,102.12-9.8,134.26a42,42,0,0,1-4.1,23,22.31,22.31,0,0,1-18.69,12.76c-9.35.69-18.47-3.64-27.81-13q-49.23-50.26-78-149.75c-22.57,45.13-39.67,79.09-50.83,101.43-20.75,39.89-38.52,60.17-53.11,61.31-9.58.69-17.78-7.29-24.39-24.16q-27-69.06-58.12-267.37a29.36,29.36,0,0,1,5.47-24.16ZM905,429.27a82.24,82.24,0,0,0-56.3-40.81,91.68,91.68,0,0,0-19.15-2q-50.59,0-82.74,52.66a185.84,185.84,0,0,0-27.35,99.15c0,27.12,5.7,50.37,16.87,69.75a82.23,82.23,0,0,0,56.3,40.8,91.59,91.59,0,0,0,19.14,2.05q51,0,82.74-52.66a187.52,187.52,0,0,0,27.35-99.83c0-27.12-5.69-50.15-16.86-69.06Zm-44.45,97.78c-4.79,23-13.68,40.34-26.9,52.2-10.25,9.34-19.83,13-28.49,11.39s-15.5-9.34-20.74-22.79a90.6,90.6,0,0,1-6.15-32.14,139.52,139.52,0,0,1,2.5-25.76A116,116,0,0,1,799.46,468c11.63-17.09,23.94-24.38,36.7-21.65,8.66,1.82,15.5,9.35,20.74,22.79a90.13,90.13,0,0,1,6.16,31.91,125.72,125.72,0,0,1-2.51,26Zm265.08-97.78a82.22,82.22,0,0,0-56.29-40.81,91.61,91.61,0,0,0-19.15-2q-50.6,0-82.74,52.66a185.84,185.84,0,0,0-27.35,99.15c0,27.12,5.7,50.37,16.86,69.75a82.3,82.3,0,0,0,56.3,40.8,91.75,91.75,0,0,0,19.15,2.05q51,0,82.74-52.66a187.52,187.52,0,0,0,27.35-99.83c0-27.12-5.69-50.15-16.87-69.06Zm-44.44,97.78c-4.79,23-13.68,40.34-26.9,52.2-10.25,9.34-19.82,13-28.49,11.39s-15.5-9.34-20.74-22.79a90.38,90.38,0,0,1-6.15-32.14,139.52,139.52,0,0,1,2.5-25.76A115.78,115.78,0,0,1,1020.1,468c11.63-17.09,23.94-24.38,36.7-21.65,8.66,1.82,15.5,9.35,20.74,22.79a90.35,90.35,0,0,1,6.16,31.91,125.72,125.72,0,0,1-2.51,26Z" transform="translate(-247.36 -13.83)" style="fill:#fff"/></svg>',
                'setting_key' => 'enable_woocommerce_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Woocommerce',
                'is_plugin' => true,
                'plugin_active' => array(
                    'woocommerce/woocommerce.php',
                ),
            ),
            'flexify_checkout' => array(
                'title' => __('Flexify Checkout para WooCommerce', 'joinotify'),
                'description' => __('Recupere vendas não finalizadas, envie o código Pix no WhatsApp e otimize a finalização de compras, garantindo mais conversões.', 'joinotify'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 945.76 891.08"><path d="M514,116.38c-234.22,0-424.08,189.87-424.08,424.07S279.74,964.53,514,964.53,938,774.67,938,540.45,748.17,116.38,514,116.38Zm171.38,426.1c-141.76.37-257.11,117.69-257.4,259.45H339.72c0-191.79,153.83-347.42,345.62-347.42Zm0-176.64c-141.76.19-266.84,69.9-346,176.13V410.6C431,328.12,551.92,277.5,685.34,277.5Z" transform="translate(-89.88 -73.45)" style="fill:#141d26"/><circle cx="779.75" cy="166.01" r="166.01" style="fill:#fff"/><path d="M785.1,285.69c-9.31-37.24-14-55.85-4.19-68.37s29-12.52,67.35-12.52h50.25c38.38,0,57.57,0,67.34,12.52s5.12,31.13-4.18,68.37c-5.93,23.68-8.89,35.52-17.72,42.42s-21,6.89-45.44,6.89H848.26c-24.41,0-36.62,0-45.45-6.89S791,309.37,785.1,285.69Z" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M954.76,210.22,947.05,182c-3-10.9-4.45-16.35-7.5-20.45a27.08,27.08,0,0,0-11.91-9.09c-4.76-1.86-10.41-1.86-21.7-1.86M792,210.22l7.7-28.27c3-10.9,4.46-16.35,7.51-20.45a27.11,27.11,0,0,1,11.9-9.09c4.77-1.86,10.42-1.86,21.71-1.86" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M840.83,150.55a10.85,10.85,0,0,1,10.85-10.85h43.41a10.85,10.85,0,1,1,0,21.7H851.68A10.85,10.85,0,0,1,840.83,150.55Z" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M830,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/><path d="M916.79,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/><path d="M873.38,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/></svg>',
                'setting_key' => 'enable_flexify_checkout_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Flexify_Checkout',
                'is_plugin' => true,
                'plugin_active' => array(
                    'flexify-checkout-for-woocommerce/flexify-checkout-for-woocommerce.php',
                ),
            ),
            'elementor' => array(
                'title' => __('Elementor', 'joinotify'),
                'description' => __('Envie mensagens quando um formulário Elementor com campo de telefone for enviado. Conecte-se com seus clientes instantaneamente.', 'joinotify'),
                'icon' => '<svg viewBox="0 0 400 400" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><g clip-path="url(#a)"><path d="M200 0C89.532 0 0 89.532 0 200c0 110.431 89.532 200 200 200s200-89.532 200-200C399.964 89.532 310.431 0 200 0Zm-49.991 283.306h-33.315V116.658h33.315v166.648Zm133.297 0h-99.982v-33.315h99.982v33.315Zm0-66.667h-99.982v-33.315h99.982v33.315Zm0-66.666h-99.982v-33.315h99.982v33.315Z" fill="#92003B"/></g><defs><clipPath id="a"><path fill="#fff" d="M0 0h400v400H0z"/></clipPath></defs></svg>',
                'setting_key' => 'enable_elementor_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Elementor',
                'is_plugin' => true,
                'plugin_active' => array(
                    'elementor/elementor.php',
                ),
            ),
            'wpforms' => array(
                'title' => __('WPForms', 'joinotify'),
                'description' => __('Automatize o envio de mensagens ao receber um formulário WPForms com telefone. Mantenha seus clientes informados em tempo real.', 'joinotify'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="none"><mask id="a" width="80" height="80" x="0" y="0" maskUnits="userSpaceOnUse"><path fill="#fff" d="M40 80c22.091 0 40-17.909 40-40S62.091 0 40 0 0 17.909 0 40s17.909 40 40 40z"/></mask><g mask="url(#a)"><path fill="#7F3E13" d="M23.383 12.337a8.038 8.038 0 017.805 8.278 7.818 7.818 0 11-15.611 0 8.035 8.035 0 017.806-8.278z"/><path fill="#B85A1B" d="M23.383 14.623a5.8 5.8 0 00-5.519 5.992 5.538 5.538 0 1011.038 0 5.8 5.8 0 00-5.519-5.992z"/><path fill="#63300F" d="M23.384 16.91a3.488 3.488 0 00-3.233 3.706 3.263 3.263 0 106.465 0 3.515 3.515 0 00-3.232-3.706z"/><path fill="#7F3E13" d="M56.615 12.337a8.037 8.037 0 017.805 8.278 7.818 7.818 0 11-15.611 0 8.035 8.035 0 017.806-8.278z"/><path fill="#B85A1B" d="M56.615 14.623a5.8 5.8 0 00-5.519 5.992 5.538 5.538 0 1011.038 0 5.8 5.8 0 00-5.519-5.992z"/><path fill="#4F2800" d="M56.616 16.91a3.488 3.488 0 00-3.233 3.706 3.263 3.263 0 106.465 0 3.515 3.515 0 00-3.232-3.706z"/><path fill="#7F3E13" d="M36.827 16.2a2.484 2.484 0 110 4.968 2.484 2.484 0 010-4.968zm6.307 0a2.484 2.484 0 110 4.969 2.484 2.484 0 010-4.969z"/><path fill="#7F3E13" d="M68.875 26.528v15.374h.158a5.716 5.716 0 013.666 5.361v11.078l-32.68 10.8-32.68-10.485V47.343a5.716 5.716 0 013.666-5.361h.158V26.489c0-10.644 57.713-10.644 57.713 0l-.001.039z"/><path fill="#B85A1B" d="M13.765 27.12v16.6l-1.34.551a3.784 3.784 0 00-2.444 3.584v11.668l30.039 9.658 30.039-9.895v-11.43a3.824 3.824 0 00-2.444-3.588l-1.34-.552v-16.6c0-7.884-52.47-7.884-52.47 0l-.04.003z"/><path fill="#E1762F" d="M13.765 27.12v16.6l-1.34.552a3.784 3.784 0 00-2.444 3.583v11.669l30.039 9.658v-6.347c-10.407.039-20.814-6.78-18.055-20.262H40.02V21.246c-13.127 0-26.254 1.971-26.254 5.913l-.001-.039z"/><path fill="#E5895B" d="M20.506 39.065h39.066c5.559 29.33-45.016 29.093-39.066 0z"/><path fill="#E5895B" d="M22.438 41.351c-.552 4.533.434 8.91 3.784 12.3 3.35 3.391 8.949 5.085 13.876 5.085a19.008 19.008 0 0013.324-4.928c3.509-3.39 4.612-7.805 4.139-12.457H22.438z"/><path fill="#FAD395" d="M46.563 55.03c2.641 3.39 10.289 2.01 8-5.164l-8 5.164z"/><path fill="#4F2800" d="M44.159 54.281c2.957 3.784 12.536 1.537 10.328-6.662l-10.328 6.662z"/><path fill="#fff" d="M46.721 52.31a1.183 1.183 0 11-.749 1.418 1.143 1.143 0 01.749-1.419zm6.268-4.337a1.685 1.685 0 100 .04v-.04z"/><path fill="#AD6151" d="M50.584 55.74a5.976 5.976 0 004.218-5.046c-1.734-.237-4.651 2.247-4.218 5.046z"/><path fill="#FAD395" d="M24.527 39.065h30.988c4.372 20.933-35.679 20.736-30.988 0z"/><path fill="#4F2800" d="M40.019 50.97a7.332 7.332 0 011.459-3.469c3.706-.591 6.938-3.39 5.913-8.476a22.275 22.275 0 00-7.331-1.261l-1.5 4.809 1.5 8.476-.041-.079z"/><path fill="#63300F" d="M40.019 50.97a7.332 7.332 0 00-1.459-3.469c-3.706-.591-6.938-3.39-5.913-8.476a22.273 22.273 0 017.332-1.261V50.97h.04z"/><path fill="#AD6151" d="M34.539 39.774c3.603-.97 7.397-.97 11 0 1.497 3.824-12.695 3.785-11 0z"/><path fill="#fff" d="M34.106 29.13a4.14 4.14 0 110 8.278 4.14 4.14 0 010-8.278z"/><path fill="#1B1D23" d="M34.422 30.549a3.076 3.076 0 11-3.075 3.075 3.114 3.114 0 013.075-3.075z"/><path fill="#fff" d="M46.011 29.13a4.14 4.14 0 100 8.278 4.14 4.14 0 000-8.278z"/><path fill="#1B1D23" d="M45.696 30.549a3.076 3.076 0 103.075 3.075 3.114 3.114 0 00-3.075-3.075z"/><path fill="#63300F" d="M37.103 27.75a11.581 11.581 0 00-8.594 1.5c-.868-5.048 7.686-6.546 8.594-1.5z"/><path fill="#4F2800" d="M42.148 25.228a11.58 11.58 0 018.594 1.5c.867-5.088-7.687-6.586-8.594-1.5z"/><path fill="#7EAABA" d="M72.66 58.42v31.813a3.863 3.863 0 01-3.863 3.863H11.163A3.863 3.863 0 017.3 90.233V58.696l32.68 7.805 32.68-8.081z"/><path fill="#D3E8EF" d="M70.058 61.771l-30.039 7.411-30.078-7.174v27.595a1.932 1.932 0 001.932 1.932h56.254a1.932 1.932 0 001.932-1.932l-.001-27.832z"/><path fill="#fff" d="M40.02 69.182L9.981 62.007v27.595a1.932 1.932 0 001.932 1.932H40.02V69.182z"/><path fill="#036AAB" d="M40.02 76.988h25.269v3.39H40.02v-3.39zm0 7.017h25.269v3.39H40.02v-3.39z"/><path fill="#0399ED" d="M14.751 76.988H40.02v3.39H14.751v-3.39zM40.02 87.395v-3.39H14.751v3.39H40.02z"/><path fill="#fff" d="M25 74.78h3.193v15.453H25V74.78z"/><path fill="#7EAABA" d="M16.13 60.785l23.85 5.716-8.515 8.042c-5.519-3.233-10.999-6.82-15.335-13.758z"/><path fill="#fff" d="M22.871 65.082a44.747 44.747 0 008.239 6.229l3.587-3.39-11.826-2.839z"/><path fill="#7EAABA" d="M63.869 60.785l-23.85 5.716 8.515 8.042c5.519-3.233 10.959-6.82 15.335-13.758z"/><path fill="#fff" d="M57.088 65.082a44.743 44.743 0 01-8.239 6.229l-3.587-3.39 11.826-2.839z"/></g></svg>',
                'setting_key' => 'enable_wpforms_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Wpforms',
                'is_plugin' => true,
                'plugin_active' => array(
                    'wpforms/wpforms.php',
                    'wpforms-lite/wpforms.php',
                ),
            ),
            'wordpress' => array(
                'title' => __('WordPress', 'joinotify'),
                'description' => __('Automatize o envio de mensagens em acionamentos de eventos no WordPress.', 'joinotify'),
                'icon' => '<svg viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>wordpress</title> <desc>Created with sketchtool.</desc> <g id="brand" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="wordpress" fill="#000000"> <path d="M12,2 C6.4859945,2 2,6.48575931 2,11.9997648 C2,17.5142407 6.4859945,22 12,22 C17.5140055,22 22.0004704,17.5142407 22.0004704,11.9997648 C22.0004704,6.48575931 17.5140055,2 12,2 M12,21.5416167 C6.73882264,21.5416167 2.4586185,17.2609422 2.4586185,11.9997648 C2.4586185,6.73882264 6.73882264,2.45838331 12,2.45838331 C17.2611774,2.45838331 21.5416167,6.73882264 21.5416167,11.9997648 C21.5416167,17.2609422 17.2611774,21.5416167 12,21.5416167 M3.42157624,11.9996943 C3.42157624,15.3953527 5.39457654,18.3300407 8.25659117,19.7202427 L4.16430302,8.50854912 C3.68828053,9.57536631 3.42157624,10.756015 3.42157624,11.9996943 M17.7912933,11.5671112 C17.7912933,10.5066441 17.4102872,9.7726193 17.0838449,9.20111009 C16.6492156,8.49436722 16.2411628,7.89628166 16.2411628,7.18906842 C16.2411628,6.40071497 16.8392483,5.66669019 17.6816952,5.66669019 C17.7197959,5.66669019 17.756015,5.67139397 17.7929396,5.67351067 C16.2667984,4.27531221 14.2333545,3.42134105 12,3.42134105 C9.0029869,3.42134105 6.36628331,4.95900656 4.83238082,7.28784779 C5.03393777,7.29443308 5.22350008,7.2981961 5.38460453,7.2981961 C6.28161528,7.2981961 7.67087655,7.18906842 7.67087655,7.18906842 C8.13325807,7.16225687 8.18782191,7.84148263 7.72591077,7.89628166 C7.72591077,7.89628166 7.26094217,7.95061032 6.7439968,7.97765705 L9.86777676,17.2702086 L11.7452903,11.6397846 L10.4089466,7.97765705 C9.94680025,7.95061032 9.50911357,7.89628166 9.50911357,7.89628166 C9.04673205,7.86876455 9.1010607,7.16225687 9.56367741,7.18906842 C9.56367741,7.18906842 10.9799854,7.2981961 11.8229027,7.2981961 C12.7199135,7.2981961 14.1094099,7.18906842 14.1094099,7.18906842 C14.5720266,7.16225687 14.6263553,7.84148263 14.1639738,7.89628166 C14.1639738,7.89628166 13.69877,7.95061032 13.182295,7.97765705 L16.282556,17.1991815 L17.1386439,14.3404596 C17.5088313,13.1532256 17.7912933,12.3013712 17.7912933,11.5671112 M12.1506621,12.7502763 L9.57651873,20.2292857 C10.3453515,20.4555375 11.1579294,20.5787765 11.9999059,20.5787765 C12.9989887,20.5787765 13.9571486,20.406383 14.8489852,20.0926409 C14.8259366,20.0557163 14.8047696,20.0166749 14.7873656,19.9743409 L12.1506621,12.7502763 Z M19.5278817,7.88423999 C19.5645711,8.15752958 19.585503,8.45057504 19.585503,8.76643383 C19.585503,9.63663303 19.422517,10.6150192 18.9330887,11.8389426 L16.3128484,19.4146146 C18.8632376,17.9277499 20.5784708,15.1647499 20.5784708,11.9998118 C20.5784708,10.5082434 20.1974647,9.10604671 19.5278817,7.88423999" id="Shape"> </path> </g> </g> </g></svg>',
                'setting_key' => 'enable_wordpress_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Wordpress',
            ),
        ));
    }


    /**
     * Render a trigger tab on builder sidebar
     * 
     * @since 1.1.0
     * @param string $slug | Integration slug (eg: 'wordpress')
     * @param string $name | Integration name (eg: esc_html__( 'WordPress', 'text-domain' ) )
     * @param string $icon_svg | SVG icon code
     * @return void
     */
    protected function render_integration_trigger_tab( $slug, $name, $icon ) {
        if ( Admin::get_setting("enable_{$slug}_integration") === 'yes' ) : ?>
            <a href="#<?php echo esc_attr( $slug ); ?>" class="nav-tab">
                <?php echo $icon; // SVG icon ?>
                <?php echo $name; ?>
            </a>
        <?php endif;
    }


    /**
     * Render the trigger content
     * 
     * @since 1.1.0
     * @param string $slug | Slug da integração (eg: 'wordpress')
     * @return void
     */
    protected function render_integration_trigger_content( $slug ) {
        if ( Admin::get_setting("enable_{$slug}_integration") === 'yes' ) : ?>
            <div id="<?php echo esc_attr( $slug ); ?>" class="nav-content triggers-group">
                <?php foreach ( Triggers::get_triggers_by_context( $slug ) as $trigger ) : ?>
                    <div class="trigger-item <?php echo esc_attr( isset( $trigger['class'] ) ? $trigger['class'] : '' ); ?>" data-context="<?php echo esc_attr( $slug ); ?>" data-trigger="<?php echo esc_attr( $trigger['data_trigger'] ); ?>">
                        <h4 class="title"><?php echo esc_html( $trigger['title'] ); ?></h4>
                        <span class="description"><?php echo esc_html( $trigger['description'] ); ?></span>

                        <?php if ( isset( $trigger['class'] ) && $trigger['class'] === 'locked' ) : ?>
                            <span class="fs-sm mt-3"><?php esc_html_e( 'Este recurso será liberado em breve', 'joinotify' ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }
}