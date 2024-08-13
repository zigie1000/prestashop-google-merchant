<?php
class googlemerchant extends Module
{
    public function __construct()
    {
        $this->name = 'googlemerchant';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google Merchant Center Feed');
        $this->description = $this->l('Generate a product feed for Google Merchant Center.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('googlemerchant')) {
            $this->warning = $this->l('No name provided');
        }
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('displayBackOfficeHeader');
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->context->controller->addCSS($this->_path . 'views/css/googlemerchant.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/googlemerchant.js');
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $googlemerchant_name = strval(Tools::getValue('googlemerchant_NAME'));
            if (!$googlemerchant_name
              || empty($googlemerchant_name)
              || !Validate::isGenericName($googlemerchant_name)) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('googlemerchant_NAME', $googlemerchant_name);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output.$this->displayForm();
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
                    'name' => 'googlemerchant_NAME',
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

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['googlemerchant_NAME'] = Configuration::get('googlemerchant_NAME');

        return $helper->generateForm($fields_form);
    }

    public function getProducts()
    {
        $sql = new DbQuery();
        $sql->select('p.id_product, p.name, p.link_rewrite, i.id_image');
        $sql->from('product', 'p');
        $sql->leftJoin('product_lang', 'pl', 'pl.id_product = p.id_product');
        $sql->leftJoin('image', 'i', 'i.id_product = p.id_product');
        $sql->where('pl.id_lang = '.(int)Context::getContext()->language->id);
        $sql->where('p.active = 1');
        return Db::getInstance()->executeS($sql);
    }

    public function generateFeed()
    {
        $products = $this->getProducts();

        $xml = new SimpleXMLElement('<products/>');

        foreach ($products as $product) {
            $item = $xml->addChild('product');
            $item->addChild('id', $product['id_product']);
            $item->addChild('name', $product['name']);
            $item->addChild('link', Context::getContext()->link->getProductLink($product['id_product']));
            $item->addChild('image', Context::getContext()->link->getImageLink($product['link_rewrite'], $product['id_image']));
        }

        return $xml->asXML();
    }
}
