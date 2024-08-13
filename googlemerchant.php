<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class googlemerchant extends Module
{
    private $cacheFile;
    private $logFile;

    public function __construct()
    {
        $this->name = 'googlemerchant';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google Merchant Center Feed');
        $this->description = $this->l('Generate a product feed for Google Merchant Center.');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->cacheFile = _PS_MODULE_DIR_ . $this->name . '/cache/feed.xml';
        $this->logFile = _PS_MODULE_DIR_ . $this->name . '/logs/feed_errors.log';

        $this->createDirectoryIfNotExists(_PS_MODULE_DIR_ . $this->name . '/cache');
        $this->createDirectoryIfNotExists(_PS_MODULE_DIR_ . $this->name . '/logs');
    }

    private function createDirectoryIfNotExists($directory)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
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

        if (Tools::isSubmit('submit'.$this->name)) {
            $this->postProcess();
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $output.$this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit'.$this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Feed URL'),
                        'name' => 'GOOGLEMERCHANT_FEED_URL',
                        'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'GOOGLEMERCHANT_FEED_URL' => Configuration::get('GOOGLEMERCHANT_FEED_URL', ''),
        );
    }

    protected function postProcess()
    {
        Configuration::updateValue('GOOGLEMERCHANT_FEED_URL', Tools::getValue('GOOGLEMERCHANT_FEED_URL'));
    }

    public function generateFeed()
    {
        header('Content-Type: application/xml');
        
        $xml = new SimpleXMLElement('<xml/>');
        $item = $xml->addChild('item');
        $item->addChild('id', 1);
        $item->addChild('title', 'Test Product');

        echo $xml->asXML();
        exit;
    }

    public function hookModuleRoutes($params)
    {
        return array(
            'module-googlemerchant-feed' => array(
                'controller' => 'feed',
                'rule' => 'googlemerchant/feed',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => 'googlemerchant',
                    'controller' => 'feed',
                ),
            ),
        );
    }
}
