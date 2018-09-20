<?php
/**
 * Intelive
 * @package Intelive Claro
 * @copyright Copyright (c) 2018 Intelive Metrics Srl
 * @author Adrian Roman
 */

namespace Intelive\Claro\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Module\ModuleListInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $config;

    protected $store;

    protected $context;

    protected $storeManager;

    protected $scopeConfig;

    protected $logger;

    protected $moduleList;

    /** @var \Intelive\Claro\Model\ClaroReportsSyncFactory */
    protected $syncFactory;

    /** @var \Intelive\Claro\Model\ResourceModel\ClaroReportsSync */
    protected $syncResourceModel;

    /**
     * Data constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ModuleListInterface $moduleList
     * @param \Intelive\Claro\Model\ClaroReportsSyncFactory $syncFactory
     * @param \Intelive\Claro\Model\ResourceModel\ClaroReportsSync $syncResourceModel
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleList,
        \Intelive\Claro\Model\ClaroReportsSyncFactory $syncFactory,
        \Intelive\Claro\Model\ResourceModel\ClaroReportsSync $syncResourceModel
    )
    {
        parent::__construct($context);
        $this->context = $context;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $context->getScopeConfig();
        $this->logger = $context->getLogger();
        $this->moduleList = $moduleList;
        $this->syncFactory = $syncFactory;
        $this->syncResourceModel = $syncResourceModel;
    }

    /**
     * @return string
     */
    public function getBaseDir()
    {
        return $this->context->getBaseDir() . '/app/code/Intelive/';
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $this->config['enabled'] = $this->scopeConfig->getValue(
            'claroconfig/general/status',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $this->config['license_key'] = $this->scopeConfig->getValue(
            'claroconfig/general/license_serial_number',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $this->config['api_key'] = $this->scopeConfig->getValue(
            'claroconfig/general/api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $this->config['api_secret'] = $this->scopeConfig->getValue(
            'claroconfig/general/api_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $this->config;
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->moduleList->getOne('Intelive_Claro')['setup_version'];
    }

    /**
     * @param $msg
     */
    public function log($msg)
    {
        $this->logger->info($msg);

        return;
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStore()
    {
        if ($this->store == null) {
            $this->store = $this->storeManager->getStore();
        }

        return $this->store;
    }

    /**
     * @return mixed
     */
     public function getLicenseKey()
     {
         $licenseKey = $this->getConfig()['license_key'];
         return $licenseKey;
     }

    /**
     * @param $payload
     * @param $type
     * @return array
     */
    public function prepareResult($payload, $type)
    {
        $this->getConfig();

        $responseIsEncoded = false;
        $responseIsCompressed = false;

        $content = $payload;
        $encoded = $this->encode($payload);
        if ($encoded) {
            $responseIsEncoded = true;
            $content = $encoded;
        }

        $compressed = $this->compress($encoded);
        if ($compressed) {
            $responseIsCompressed = true;
            $content = $compressed;
        }

        // Get the id of the last returned entity
        $lastId = $this->syncResourceModel->getLastIdDate($type);

        // $this->decode($this->deCompress($compressed))

        return [
            'isEncoded' => $responseIsEncoded,
            'isCompressed' => $responseIsCompressed,
            'content' => $content,
            'type' => $type,
            'lastId' => $lastId
        ];
    }

    /**
     * @param $data
     * @return string
     */
    protected function compress($data)
    {
        return base64_encode(gzcompress(serialize(($data))));
    }

    /**
     * @param $data
     * @return mixed
     */
    protected function deCompress($data) {
        return unserialize(gzuncompress(base64_decode($data)));
    }

    /**
     * @param $payload
     * @return string
     */
    protected function encode($payload)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt(json_encode($payload), 'aes-256-cbc', $this->config['api_secret'], 0, $iv);

        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * @param $payload
     * @return string
     */
    protected function decode($payload)
    {
        list($encrypted_data, $iv) = explode('::', base64_decode($payload), 2);
        return json_decode(openssl_decrypt($encrypted_data, 'aes-256-cbc', $this->config['api_secret'], 0, $iv));
    }

    /**
     * @param $entityId
     * @param $entity
     */
    public function saveSyncData($entityId, $entity)
    {
        try {
            $syncModel = $this->syncFactory->create();
            $syncModel
                ->setData('entity', $entity)
                ->setData('last_sent_id', $entityId)
                ->setData('last_sent_date', date('Y-m-d H:i:s'));
            $this->syncResourceModel->save($syncModel);
        } catch (\Exception $e) {
        }
    }
}