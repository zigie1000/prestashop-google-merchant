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

    public function generateFeed()
    {
        header('Content-Type: application/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><xml><item><id>1</id><title>Test Product</title></item></xml>';
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
