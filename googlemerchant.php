<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class googlemerchant extends Module
{
    private $feedFile;
    private $logFile;

    public function __construct()
    {
        $this->name = 'googlemerchant';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Marco Zagato';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google Merchant Center Feed');
        $this->description = $this->l('Generate a product feed for Google Merchant Center.');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->feedFile = _PS_MODULE_DIR_ . 'googlemerchant/feed.xml';
        $this->logFile = _PS_MODULE_DIR_ . 'googlemerchant/logs/feed_errors.log';
    }

    public function install()
    {
        return parent::install() && $this->registerHook('displayBackOfficeHeader');
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/googlemerchant.css');
        $this->context->controller->addJS($this->_path . 'views/js/googlemerchant.js');
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $url = strval(Tools::getValue('GOOGLEMERCHANT_FEED_URL'));
            if (!$url || empty($url)) {
                $output .= $this->displayError($this->l('Invalid URL value'));
            } else {
                Configuration::updateValue('GOOGLEMERCHANT_FEED_URL', $url);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Google Merchant Center Feed'),
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Feed URL'),
                        'name' => 'GOOGLEMERCHANT_FEED_URL',
                        'size' => 20,
                        'required' => true,
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => array(
                'GOOGLEMERCHANT_FEED_URL' => Tools::getValue('GOOGLEMERCHANT_FEED_URL', Configuration::get('GOOGLEMERCHANT_FEED_URL')),
            ),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function generateFeed()
    {
        $products = $this->getProducts();
        if (!$products) {
            $this->logError('No products found.');
            return false;
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');
        $channel = $xml->addChild('channel');
        $channel->addChild('title', Configuration::get('PS_SHOP_NAME'));
        $channel->addChild('link', Tools::getHttpHost(true) . __PS_BASE_URI__);
        $channel->addChild('description', $this->l('Product feed for Google Merchant Center'));

        foreach ($products as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', htmlspecialchars($product['id_product']));
            $item->addChild('g:title', htmlspecialchars($product['name']));
            $item->addChild('g:link', htmlspecialchars($this->context->link->getProductLink($product['id_product'], $product['link_rewrite'])));
            $item->addChild('g:description', htmlspecialchars(strip_tags($product['description'])));
            $currency = 'ZAR';  // Assuming South African Rands as the currency
            $item->addChild('g:price', number_format($product['price'], 2, '.', '') . ' ' . $currency);
            $item->addChild('g:image_link', htmlspecialchars($this->context->link->getImageLink($product['link_rewrite'], $product['id_image'])));
            $item->addChild('g:availability', $product['quantity'] > 0 ? 'in stock' : 'out of stock');
            $item->addChild('g:brand', htmlspecialchars($product['manufacturer_name']) ?: 'Unknown');
            $item->addChild('g:gtin', htmlspecialchars($product['ean13']) ?: '');
            $item->addChild('g:mpn', htmlspecialchars($product['id_product']));
            $item->addChild('g:condition', 'new');

            // Additional fields expected by Google
            $item->addChild('g:product_type', htmlspecialchars($product['category_name']) ?? '');
            $item->addChild('g:google_product_category', isset($product['google_product_category']) ? htmlspecialchars($product['google_product_category']) : '');
            $item->addChild('g:shipping_weight', htmlspecialchars($product['weight']) . ' kg');
        }

        // Save the XML content to the feed.xml file
        $xml->asXML($this->feedFile);

        // Output the XML content
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml->asXML();
        exit;
    }

    public function getProducts()
    {
        $sql = 'SELECT p.id_product, pl.name, pl.description, p.price, i.id_image, pl.link_rewrite, m.name as manufacturer_name, p.ean13, p.quantity, cl.name as category_name, p.weight
                FROM ' . _DB_PREFIX_ . 'product p
                JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id . '
                LEFT JOIN ' . _DB_PREFIX_ . 'image i ON p.id_product = i.id_product AND i.cover = 1
                LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON p.id_manufacturer = m.id_manufacturer
                LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON p.id_category_default = cl.id_category AND cl.id_lang = ' . (int)$this->context->language->id . '
                WHERE p.active = 1';

        return Db::getInstance()->executeS($sql);
    }

    private function logError($message)
    {
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}
