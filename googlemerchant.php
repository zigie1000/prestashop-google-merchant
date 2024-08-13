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
            $feed_url = strval(Tools::getValue('GOOGLEMERCHANT_FEED_URL'));
            if (!$feed_url || empty($feed_url)) {
                $output .= $this->displayError($this->l('Invalid Feed URL'));
            } else {
                Configuration::updateValue('GOOGLEMERCHANT_FEED_URL', $feed_url);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
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
                    'required' => true,
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ),
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['GOOGLEMERCHANT_FEED_URL'] = Configuration::get('GOOGLEMERCHANT_FEED_URL');

        return $helper->generateForm($fields_form);
    }

    public function getProducts()
    {
        $sql = new DbQuery();
        $sql->select('pl.name, p.id_product, p.link_rewrite, i.id_image');
        $sql->from('product', 'p');
        $sql->leftJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . (int)Context::getContext()->language->id);
        $sql->leftJoin('image', 'i', 'i.id_product = p.id_product');
        $sql->where('p.active = 1');
        return Db::getInstance()->executeS($sql);
    }

    public function generateFeed()
    {
        $products = $this->getProducts();
        if (!$products) {
            return false;
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');
        $channel = $xml->addChild('channel');
        $channel->addChild('title', Configuration::get('PS_SHOP_NAME'));
        $channel->addChild('link', Tools::getHttpHost(true) . __PS_BASE_URI__);
        $channel->addChild('description', $this->l('Product feed for Google Merchant Center'));

        foreach ($products as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', $product['id_product']);
            $item->addChild('g:title', htmlspecialchars($product['name']));
            $item->addChild('g:link', htmlspecialchars($this->context->link->getProductLink($product['id_product'])));
            $item->addChild('g:image_link', htmlspecialchars($this->context->link->getImageLink($product['link_rewrite'], $product['id_image'])));
            $item->addChild('g:condition', 'new');
            $item->addChild('g:availability', 'in stock');
            $item->addChild('g:price', '100.00 USD');
        }

        $feed_path = _PS_MODULE_DIR_ . $this->name . '/feed.xml';
        if (!file_put_contents($feed_path, $xml->asXML())) {
            return false;
        }

        return $xml->asXML();
    }

    public function logError($message)
    {
        $log_path = _PS_MODULE_DIR_ . $this->name . '/logs/feed_errors.log';
        file_put_contents($log_path, $message . PHP_EOL, FILE_APPEND);
    }
}
