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
        $this->author = 'Your Name';
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
        if (file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile)) < 86400) {
            // Serve cached file if it's less than 24 hours old
            return file_get_contents($this->cacheFile);
        }

        $products = $this->getProducts();
        if (empty($products)) {
            $this->logError('No products found.');
            return false;
        }

        $xml = new SimpleXMLElement('<xml/>');

        foreach ($products as $product) {
            try {
                // Data validation before adding to feed
                if (!$this->validateProductData($product)) {
                    $this->logError('Invalid product data for product ID ' . $product['id_product']);
                    continue;
                }

                $item = $xml->addChild('item');
                $item->addChild('id', $product['id_product']);
                $item->addChild('title', htmlspecialchars($product['name']));
                $item->addChild('description', htmlspecialchars($product['description_short']));
                $item->addChild('link', $this->context->link->getProductLink($product['id_product']));
                $item->addChild('image_link', $this->context->link->getImageLink($product['link_rewrite'], $product['id_image']));
                $item->addChild('price', $product['price']);
                $item->addChild('condition', 'new');
                $item->addChild('availability', 'in stock');
            } catch (Exception $e) {
                $this->logError('Error adding product ID ' . $product['id_product'] . ' to feed: ' . $e->getMessage());
                continue;
            }
        }

        // Save the feed to cache
        $feed = $xml->asXML();
        if ($feed !== false) {
            file_put_contents($this->cacheFile, $feed);
        } else {
            $this->logError('Failed to generate XML feed.');
        }

        return $feed;
    }

    private function getProducts()
    {
        try {
            $sql = new DbQuery();
            $sql->select('p.id_product, pl.name, pl.description_short, pl.link_rewrite, i.id_image, p.price');
            $sql->from('product', 'p');
            $sql->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id);
            $sql->leftJoin('image', 'i', 'p.id_product = i.id_product AND i.cover = 1');
            $sql->where('p.active = 1');

            return Db::getInstance()->executeS($sql);
        } catch (Exception $e) {
            $this->logError('Database error: ' . $e->getMessage());
            return [];
        }
    }

    private function validateProductData($product)
    {
        return !empty($product['name']) && !empty($product['description_short']) && !empty($product['price']) && $product['price'] > 0;
    }

    private function logError($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
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
