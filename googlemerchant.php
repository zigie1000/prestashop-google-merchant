<?php

function log_debug($message) {
    $logfile = _PS_MODULE_DIR_ . 'googlemerchant/logs/debug.log';
    file_put_contents($logfile, date('[Y-m-d H:i:s] ') . $message . "
", FILE_APPEND);
}


if (!defined('_PS_VERSION_')) {
    exit;
}


// Clear logs every time the feed is called
$logfile_debug = _PS_MODULE_DIR_ . 'googlemerchant/logs/debug.log';
$logfile_errors = _PS_MODULE_DIR_ . 'googlemerchant/logs/feed_errors.log';
file_put_contents($logfile_debug, '');
file_put_contents($logfile_errors, '');
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

        $this->DB_PREFIX_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

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
            if (!$url or empty($url)) {
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
        
        
        

        foreach ($products as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', htmlspecialchars($product['id_product']));
            $item->addChild('g:title', htmlspecialchars($product['name']));
            $item->addChild('g:link', htmlspecialchars($this->context->link->getProductLink($product['id_product'], $product['link_rewrite'])));
            
    // Clean HTML-escaped characters from the description
    $cleaned_description = str_replace(
        ['&lt;', '&gt;', '&amp;'], 
        ['<', '>', '&'], 
        $product['description']
    );
    $item->addChild('g:description', htmlspecialchars(strip_tags($cleaned_description, '<p><br>')));
    

            // Fix for price format
            $price = number_format((float)$product['price'], 2, '.', '') . ' ZAR';
            $this->logError('Product ID: ' . $product['id_product'] . ' - Price: ' . $price);

            $price = number_format((float)$product['price'], 2, '.', '') . ' ZAR';
log_debug('Price: ' . $price);
try { if (!empty($price)) { if (!empty($price)) { log_debug('Adding price to XML: ' . $price); if (!empty($price)) { log_debug('Price before XML: ' . htmlspecialchars($price)); if (!empty(htmlspecialchars($price))) { $item->addChild('g:price', htmlspecialchars(htmlspecialchars($price))); } } } else { log_debug('Price not added: ' . $price); } } else { log_debug('Price not added: ' . $price); } } catch (Exception $e) {
log_debug('Failed to add image_link: ' . htmlspecialchars($main_image_link) . ' with error: ' . $e->getMessage()); log_debug('Failed to add price: ' . $price); }

            // Ensure main image link is correctly formed
            
    try {
        $main_image_link = $this->context->link->getImageLink($product['link_rewrite'], $product['id_image']);
        if (!$main_image_link) {
            error_log("Warning: Image link could not be generated for product ID {$product['id_product']}. Using the original URL provided.");
        }
    } catch (Exception $e) {
        error_log("Error generating image link for product ID {$product['id_product']}: " . $e->getMessage());
    }
    
$this->logError('Product ID: ' . $product['id_product'] . ' - Main Image Link Generated: ' . $main_image_link);
            $this->logError('Product ID: ' . $product['id_product'] . ' - Main Image Link: ' . $main_image_link);

            if (!$main_image_link) {
                $this->logError('Image not found for product ID ' . $product['id_product']);
                $main_image_link = 'https://via.placeholder.com/300'; // Placeholder image URL
$this->logError('Image not found for product ID ' . $product['id_product'] . ', using placeholder.');
            }
            
    try {
        $main_image_link = $this->context->link->getImageLink($product['link_rewrite'], $product['id_image']);
        if (!$main_image_link) {
            error_log("Warning: Image link could not be generated for product ID {$product['id_product']}. Using the original URL provided.");
        }
    } catch (Exception $e) {
        error_log("Error generating image link for product ID {$product['id_product']}: " . $e->getMessage());
    }
    
$this->logError('Product ID: ' . $product['id_product'] . ' - Main Image Link Generated: ' . $main_image_link);
if (!$main_image_link) {
    $main_image_link = 'https://via.placeholder.com/300'; // Placeholder image URL
$this->logError('Image not found for product ID ' . $product['id_product'] . ', using placeholder.');
}
log_debug('Image Link: ' . htmlspecialchars($main_image_link));
try { if (!empty(htmlspecialchars($main_image_link))) { if (!empty(htmlspecialchars($main_image_link))) { log_debug('Adding image_link to XML: ' . htmlspecialchars($main_image_link)); if (!empty(htmlspecialchars($main_image_link))) { log_debug('Image link before XML: ' . htmlspecialchars(htmlspecialchars($main_image_link))); if (!empty(htmlspecialchars(htmlspecialchars($main_image_link)))) { $item->addChild('g:image_link', htmlspecialchars(htmlspecialchars(htmlspecialchars($main_image_link)))); } } } else { log_debug('Image link not added: ' . htmlspecialchars($main_image_link)); } } else { log_debug('Image link not added: ' . htmlspecialchars($main_image_link)); } } catch (Exception $e) {
log_debug('Failed to add image_link: ' . htmlspecialchars($main_image_link) . ' with error: ' . $e->getMessage()); log_debug('Failed to add image_link: ' . htmlspecialchars($main_image_link)); }

            // Add additional images
            $additional_images = $this->getAdditionalImages($product['id_product']);
            foreach ($additional_images as $additional_image) {
                $item->addChild('g:additional_image_link', htmlspecialchars($additional_image));
            }

            log_debug('Availability: ' . $product['quantity'] > 0 ? 'in stock' : 'out of stock');
try { if (!empty($product['quantity'] > 0 ? 'in stock' : 'out of stock')) { if (!empty($product['quantity'] > 0 ? 'in stock' : 'out of stock')) { log_debug('Adding availability to XML: ' . $product['quantity'] > 0 ? 'in stock' : 'out of stock'); if (!empty($product['quantity'] > 0 ? 'in stock' : 'out of stock')) { log_debug('Availability before XML: ' . $product['quantity'] > 0 ? 'in stock' : 'out of stock'); if (!empty($product['quantity'] > 0 ? 'in stock' : 'out of stock')) { $item->addChild('g:availability', $product['quantity'] > 0 ? 'in stock' : 'out of stock'); } } } else { log_debug('Availability not added: ' . $product['quantity'] > 0 ? 'in stock' : 'out of stock'); } } else { log_debug('Availability not added: ' . $product['quantity'] > 0 ? 'in stock' : 'out of stock'); } } catch (Exception $e) {
log_debug('Failed to add image_link: ' . htmlspecialchars($main_image_link) . ' with error: ' . $e->getMessage()); log_debug('Failed to add availability: ' . $product['quantity'] > 0 ? 'in stock' : 'out of stock'); }
            $item->addChild('g:brand', htmlspecialchars($product['manufacturer_name']) ?: 'Unknown');
            if (!empty($product['ean13']) && strtolower($product['ean13']) != 'null') {
    $item->addChild('g:gtin', htmlspecialchars($product['ean13']));
}
            $item->addChild('g:mpn', htmlspecialchars($product['id_product']));
            $item->addChild('g:condition', 'new');

            // Additional fields expected by Google
            $item->addChild('g:product_type', htmlspecialchars($product['category_name']) ?? '');
            $item->addChild('g:google_product_category', $this->getGoogleCategory($product['id_category_default']));
            $item->addChild('g:shipping_weight', htmlspecialchars($product['weight']) . ' kg');
        }

        // Save the XML content to the feed.xml file
        $xml->asXML($this->feedFile);

        // Output the XML content
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml->asXML();
        exit;
    }

    private function getGoogleCategory($id_category)
    {
        // Fetch the category name from the database using the ID
        $categoryName = Db::getInstance()->getValue('SELECT name FROM ' . _DB_PREFIX_ . 'category_lang WHERE id_category = ' . (int)$id_category);

        // Load the mapping from the CSV
        $mapping = $this->getCategoryMapping();

        // Return the mapped Google category or a default value
        return isset($mapping[$categoryName]) ? htmlspecialchars($mapping[$categoryName]) : 'Miscellaneous';
    }

    private function getCategoryMapping()
    {
        $filePath = _PS_MODULE_DIR_ . 'googlemerchant/mapping.csv';
        $mapping = [];

        if (file_exists($filePath)) {
            $handle = fopen($filePath, 'r');
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $prestashopCategory = trim($data[0]);  // Trimming any extra spaces
                $googleCategory = trim($data[1]);  // Trimming any extra spaces
                $mapping[$prestashopCategory] = $googleCategory;
            }
            fclose($handle);
        }

        return $mapping;
    }

    public function getProducts()
    {
        $sql = 'SELECT p.id_product, pl.name, pl.description, p.price, i.id_image, pl.link_rewrite, m.name as manufacturer_name, p.ean13, p.quantity, cl.name as category_name, p.weight, p.id_category_default
                FROM ' . _DB_PREFIX_ . 'product p
                JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id . '
                LEFT JOIN ' . _DB_PREFIX_ . 'image i ON p.id_product = i.id_product AND i.cover = 1
                LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON p.id_manufacturer = m.id_manufacturer
                LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON p.id_category_default = cl.id_category AND cl.id_lang = ' . (int)$this->context->language->id . '
                WHERE p.active = 1';

        return Db::getInstance()->executeS($sql);
    }

    private function getAdditionalImages($id_product)
    {
        $images = [];
        $sql = 'SELECT id_image FROM ' . _DB_PREFIX_ . 'image WHERE id_product = ' . (int)$id_product . ' AND cover = 0';
        $result = Db::getInstance()->executeS($sql);

        foreach ($result as $row) {
            $image_link = $this->getImageWithDynamicExtension($row['id_image']);
            if ($image_link && strpos($image_link, ".jpg") !== false) {  // Ensure valid .jpg URL or other formats
                $images[] = $image_link;
            }
        }

        return $images;
    }

    
    private function getImageWithDynamicExtension($image_id)
    {
        // Check for multiple possible file formats (jpg, png, gif)
        $possible_extensions = ['jpg', 'png', 'gif'];
        foreach ($possible_extensions as $ext) {
            $image_path = _PS_IMG_DIR_ . 'p/' . floor($image_id / 100) . '/' . $image_id . '.' . $ext;
            if (file_exists($image_path)) {
                return $this->context->link->getImageLink(null, $image_id) . '.' . $ext;
            }
        }

        // Return null if no valid image is found
        return null;
    }

    private function logError($message)
    {
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}
