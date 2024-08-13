<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class googlemerchant extends Module
{
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

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Feed URL'),
                    'name' => 'GOOGLEMERCHANT_FEED_URL',
                    'size' => 20,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit' . $this->name;
        $helper->fields_value['GOOGLEMERCHANT_FEED_URL'] = Configuration::get('GOOGLEMERCHANT_FEED_URL');

        return $helper->generateForm($fields_form);
    }

    private function getProducts()
    {
        // Fetch the products from your store database
        $sql = 'SELECT p.id_product as id, pl.name as title, pl.description_short as description, 
                    CONCAT(\'https://dealbrut.com/\', p.id_product, \'-\', pl.link_rewrite) as link,
                    CONCAT(\'https://dealbrut.com/img/p/\', i.id_image, \'-large_default.jpg\') as image_link,
                    CONCAT(p.price, \' USD\') as price,
                    IF(p.quantity > 0, \'in stock\', \'out of stock\') as availability,
                    m.name as brand,
                    p.reference as mpn,
                    p.ean13 as gtin,
                    `new` as `condition`
                FROM ' . _DB_PREFIX_ . 'product p
                JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id . ')
                JOIN ' . _DB_PREFIX_ . 'manufacturer m ON (p.id_manufacturer = m.id_manufacturer)
                JOIN ' . _DB_PREFIX_ . 'image i ON (p.id_product = i.id_product AND i.cover = 1)
                WHERE p.active = 1';

        return Db::getInstance()->executeS($sql);
    }

    public function generateFeed()
    {
        $products = $this->getProducts();
        $xml = new SimpleXMLElement('<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');
        $channel = $xml->addChild('channel');
        $channel->addChild('title', 'Dealbrut.com Product Feed');
        $channel->addChild('link', 'https://dealbrut.com');
        $channel->addChild('description', 'Google Merchant Center Feed for Dealbrut.com');

        foreach ($products as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', $product['id']);
            $item->addChild('g:title', $product['title']);
            $item->addChild('g:description', $product['description']);
            $item->addChild('g:link', $product['link']);
            $item->addChild('g:image_link', $product['image_link']);
            $item->addChild('g:price', $product['price']);
            $item->addChild('g:availability', $product['availability']);
            $item->addChild('g:brand', $product['brand']);
            $item->addChild('g:mpn', $product['mpn']);
            $item->addChild('g:gtin', $product['gtin']);
            $item->addChild('g:condition', $product['condition']);
        }

        return $xml->asXML();
    }

    public function logError($message)
    {
        $logFile = _PS_MODULE_DIR_ . $this->name . '/logs/feed_errors.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}
