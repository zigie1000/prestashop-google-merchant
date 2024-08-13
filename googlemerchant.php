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
        $this->feedFile = _PS_MODULE_DIR_ . 'googlemerchant/cache/feed.xml';
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

    public function generateFeed()
    {
        $products = $this->getProducts();
        if (!$products) {
            $this->logError('No products found.');
            return false;
        }

        $xml = new SimpleXMLElement('<rss version="2.0" encoding="UTF-8" xmlns:g="http://base.google.com/ns/1.0"></rss>');
        $channel = $xml->addChild('channel');
        $channel->addChild('title', Configuration::get('PS_SHOP_NAME'));
        $channel->addChild('link', Tools::getHttpHost(true) . __PS_BASE_URI__);
        $channel->addChild('description', $this->l('Product feed for Google Merchant Center'));

        foreach ($products as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', htmlspecialchars($product['id_product']));
            $item->addChild('g:title', htmlspecialchars($product['name']));
            $item->addChild('g:link', htmlspecialchars($this->context->link->getProductLink($product['id_product'], $product['link_rewrite'])));
            $item->addChild('g:description', htmlspecialchars(strip_tags($product['description_short'])));
            $item->addChild('g:price', number_format($product['price'], 2, '.', '') . ' ' . $this->context->currency->iso_code);
            $item->addChild('g:image_link', htmlspecialchars($this->context->link->getImageLink($product['link_rewrite'], $product['id_image'])));
            $item->addChild('g:availability', $product['quantity'] > 0 ? 'in stock' : 'out of stock');
            $item->addChild('g:brand', htmlspecialchars($product['manufacturer_name'] ?? 'Unknown'));
            $item->addChild('g:condition', 'new');
            $item->addChild('g:mpn', htmlspecialchars($product['id_product']));
        }

        try {
            $feedContent = $xml->asXML();
            if (file_put_contents($this->feedFile, $feedContent) === false) {
                $this->logError('Failed to write feed file.');
                return false;
            }
        } catch (Exception $e) {
            $this->logError('Failed to generate XML feed: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    private function logError($message)
    {
        file_put_contents($this->logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }

    private function getProducts()
    {
        $sql = new DbQuery();
        $sql->select('p.id_product, pl.name, pl.description_short, p.price, p.link_rewrite, m.name as manufacturer_name, i.id_image, sa.quantity');
        $sql->from('product', 'p');
        $sql->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int)Context::getContext()->language->id);
        $sql->leftJoin('image', 'i', 'p.id_product = i.id_product AND i.cover = 1');
        $sql->leftJoin('manufacturer', 'm', 'p.id_manufacturer = m.id_manufacturer');
        $sql->leftJoin('stock_available', 'sa', 'p.id_product = sa.id_product');
        $sql->where('p.active = 1');
        return Db::getInstance()->executeS($sql);
    }
}
