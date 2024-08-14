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
            $item->addChild('g:link', htmlspecialchars($this->context->link->getProductLink($product['id_product'])));
            $item->addChild('g:description', htmlspecialchars(strip_tags($product['description'])));
            $item->addChild('g:price', Tools::displayPrice($product['price'], $this->context->currency));
            $item->addChild('g:image_link', htmlspecialchars($this->context->link->getImageLink($product['link_rewrite'], $product['id_image'])));
            $item->addChild('g:availability', $product['quantity'] > 0 ? 'in stock' : 'out of stock');
            $item->addChild('g:brand', htmlspecialchars($product['manufacturer_name']));
            $item->addChild('g:gtin', !empty($product['ean13']) ? $product['ean13'] : 'Unknown');
            $item->addChild('g:mpn', htmlspecialchars($product['id_product']));
            $item->addChild('g:condition', 'new');
        }

        // Save the XML content to the feed.xml file
        $filePath = _PS_MODULE_DIR_ . 'googlemerchant/feed.xml';
        $xml->asXML($filePath);

        // Output the XML content
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml->asXML();
        exit;
    }

    public function getProducts()
    {
        $sql = 'SELECT p.id_product, pl.name, pl.description, p.price, i.id_image, pl.link_rewrite, p.quantity, m.name as manufacturer_name, p.ean13
                FROM ' . _DB_PREFIX_ . 'product p
                JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id . '
                LEFT JOIN ' . _DB_PREFIX_ . 'image i ON p.id_product = i.id_product AND i.cover = 1
                LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON p.id_manufacturer = m.id_manufacturer
                WHERE p.active = 1';

        return Db::getInstance()->executeS($sql);
    }

    private function logError($message)
    {
        $logFile = _PS_MODULE_DIR_ . 'googlemerchant/logs/feed_errors.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}
