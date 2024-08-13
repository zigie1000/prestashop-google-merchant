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
        $xml = new SimpleXMLElement('<products/>');

        foreach ($products as $product) {
            $item = $xml->addChild('product');
            $item->addChild('id', $product['id_product']);
            $item->addChild('title', htmlspecialchars($product['name']));
            $item->addChild('description', htmlspecialchars(strip_tags($product['description'])));
            $item->addChild('link', $this->context->link->getProductLink($product['id_product']));
            $item->addChild('image_link', $this->context->link->getImageLink($product['link_rewrite'], $product['id_image']));
            $item->addChild('condition', 'new');
            $item->addChild('price', Tools::displayPrice($product['price']));
            $item->addChild('availability', $product['quantity'] > 0 ? 'in stock' : 'out of stock');
        }

        // Set Content-Type to XML for correct rendering in browser or API clients
        header('Content-Type: application/xml');
        
        return $xml->asXML();
    }

    public function getProducts()
    {
        $sql = 'SELECT p.id_product, pl.name, pl.description, p.price, i.id_image, pl.link_rewrite, p.quantity
                FROM ' . _DB_PREFIX_ . 'product p
                JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id . '
                LEFT JOIN ' . _DB_PREFIX_ . 'image i ON p.id_product = i.id_product AND i.cover = 1
                WHERE p.active = 1';

        return Db::getInstance()->executeS($sql);
    }
}
