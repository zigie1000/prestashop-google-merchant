<?php

class GoogleMerchantFeedModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $feed = $this->module->generateFeed();

        if ($feed === false) {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'Error generating feed. Please check logs for more details.';
            exit;
        }

        header('Content-Type: application/xml');
        echo $feed;
        exit;
    }
}
