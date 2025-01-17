<?php
/**
 * Intelive
 * @package Intelive Claro
 * @copyright Copyright (c) 2018 Intelive Metrics Srl
 * @author Adrian Roman
 */

namespace Intelive\Claro\Model\Types;

use Monolog\Logger;

class Products
{

    /** @var array */
    public $products = [];

    /** @var \Intelive\Claro\Helper\Data */
    protected $helper;

    /** @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory */
    protected $productFactory;

    /** @var \Magento\Framework\ObjectManagerInterface */
    protected $objectManager;

    /** @var string */
    private $pageNum;

    /** @var array */
    private $productAttributes = [];

    /**
     * @param \Intelive\Claro\Helper\Data $helper
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productFactory
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        \Intelive\Claro\Helper\Data $helper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->helper = $helper;
        $this->productFactory = $productFactory;
        $this->objectManager = $objectManager;
    }

    /**
     * @param $pageSize
     * @param $pageNum
     * @param null $startDate
     * @param null $endDate
     * @param $sortDir
     * @param $filterBy
     * @param $id
     * @param $fromId
     * @return $this|array
     */
    public function load($pageSize, $pageNum, $startDate = null, $endDate = null, $sortDir = null, $filterBy = null, $id = null, $fromId = null)
    {
        try {
            $this->pageNum = $pageNum;
            $this->getProductAttributes();
            if ($id) {
                $collection = $this->productFactory->create()
                    ->addAttributeToSelect('*')
                    ->addAttributeToFilter('entity_id', $id);
            } elseif ($startDate && $endDate) {
                $from = date('Y-m-d 00:00:00', strtotime($startDate));
                $to = date('Y-m-d 23:59:59', strtotime($endDate));
                $collection = $this->productFactory->create()
                    ->addAttributeToSelect('*')
                    ->addAttributeToFilter($filterBy, array('from' => $from, 'to' => $to));
            } else {
                $collection = $this->productFactory->create()
                    ->addAttributeToSelect('*');
            }
            if ($fromId) {
                $collection->addFieldToFilter('entity_id', ['gt' => $fromId]);
            }

            $collection->setOrder('entity_id', $sortDir);
            $collection->setCurPage($pageNum);
            $collection->setPageSize($pageSize);
            if ($collection->getLastPageNumber() < $pageNum) {
                return $this;
            }

            $lastId = [];
            $returnedIds = [];
            /** @var \Magento\Catalog\Model\Product $product */
            foreach ($collection as $product) {
                $model = $this
                    ->objectManager
                    ->create('\Intelive\Claro\Model\Types\Product')
                    ->parse(
                        $product, $this->productAttributes
                    );
                if ($model) {
                    $returnedIds[] = $product->getId();
                    $this->products[] = $model;
                    $lastId[] = $product->getId();
                }
            }

            return [
                'data' => $this->products,
                'last_id' => !empty($lastId) ? max($lastId) : 0,
                'returned_ids' => $returnedIds
            ];

        } catch (\Exception $ex) {
            $this->helper->log($ex->getMessage() . ' Trace ' . $ex->getTraceAsString(), Logger::CRITICAL);

            return [];
        }
    }

    protected function getProductAttributes()
    {
        if (!$this->productAttributes) {
            $attributes = $this->objectManager->get('\Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection')
                ->addFieldToFilter(\Magento\Eav\Model\Entity\Attribute\Set::KEY_ENTITY_TYPE_ID, 4)
                ->load()
                ->getItems();
            foreach ($attributes as $attribute) {
                if (!$attribute) {
                    continue;
                }
                $this->productAttributes[$attribute->getData('attribute_code')] = $attribute->usesSource();
            }
        }
    }
}
