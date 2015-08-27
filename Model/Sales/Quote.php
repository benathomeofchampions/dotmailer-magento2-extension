<?php

namespace Dotdigitalgroup\Email\Model\Sales;

class Quote
{
    //customer
    const XML_PATH_LOSTBASKET_CUSTOMER_ENABLED_1        = 'abandoned_carts/customers/enabled_1';
    const XML_PATH_LOSTBASKET_CUSTOMER_ENABLED_2        = 'abandoned_carts/customers/enabled_2';
    const XML_PATH_LOSTBASKET_CUSTOMER_ENABLED_3        = 'abandoned_carts/customers/enabled_3';
    const XML_PATH_LOSTBASKET_CUSTOMER_INTERVAL_1       = 'abandoned_carts/customers/send_after_1';
    const XML_PATH_LOSTBASKET_CUSTOMER_INTERVAL_2       = 'abandoned_carts/customers/send_after_2';
    const XML_PATH_LOSTBASKET_CUSTOMER_INTERVAL_3       = 'abandoned_carts/customers/send_after_3';
    const XML_PATH_LOSTBASKET_CUSTOMER_CAMPAIGN_1       = 'abandoned_carts/customers/campaign_1';
    const XML_PATH_LOSTBASKET_CUSTOMER_CAMPAIGN_2       = 'abandoned_carts/customers/campaign_2';
    const XML_PATH_LOSTBASKET_CUSTOMER_CAMPAIGN_3       = 'abandoned_carts/customers/campaign_3';

    //guest
    const XML_PATH_LOSTBASKET_GUEST_ENABLED_1           = 'abandoned_carts/guests/enabled_1';
    const XML_PATH_LOSTBASKET_GUEST_ENABLED_2           = 'abandoned_carts/guests/enabled_2';
    const XML_PATH_LOSTBASKET_GUEST_ENABLED_3           = 'abandoned_carts/guests/enabled_3';
    const XML_PATH_LOSTBASKET_GUEST_INTERVAL_1          = 'abandoned_carts/guests/send_after_1';
    const XML_PATH_LOSTBASKET_GUEST_INTERVAL_2          = 'abandoned_carts/guests/send_after_2';
    const XML_PATH_LOSTBASKET_GUEST_INTERVAL_3          = 'abandoned_carts/guests/send_after_3';
    const XML_PATH_LOSTBASKET_GUEST_CAMPAIGN_1          = 'abandoned_carts/guests/campaign_1';
    const XML_PATH_LOSTBASKET_GUEST_CAMPAIGN_2          = 'abandoned_carts/guests/campaign_2';
    const XML_PATH_LOSTBASKET_GUEST_CAMPAIGN_3          = 'abandoned_carts/guests/campaign_3';


	/**
	 * number of lost baskets available.
	 * @var array
	 */
	public $lostBasketCustomers = array(1, 2, 3);
	/**
	 * number of guest lost baskets available.
	 * @var array
	 */
	public $lostBasketGuests = array(1, 2, 3);

	protected $_helper;
	/**
	 * @var \Magento\Framework\Stdlib\DateTime
	 */
	protected $dateTime;
	protected $scopeConfig;
	protected $_storeManager;
	protected $_objectManager;
	protected $quoteCollection;


	public function __construct(
		\Dotdigitalgroup\Email\Helper\Data $helper,
		\Magento\Framework\Stdlib\DateTime $dateTime,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Quote\Model\Resource\Quote\CollectionFactory $collectionFactory
	)
	{
		$this->_helper = $helper;
		$this->dateTime = $dateTime;
		$this->_storeManager = $storeManager;
		$this->_objectManager = $objectManager;
		$this->quoteCollection = $collectionFactory->create();
		$this->scopeConfig = $scopeConfig;
	}


	/**
	 * Proccess abandoned carts.
	 *
	 * @param string $mode
	 */
    public function proccessAbandonedCarts($mode = 'all')
    {
        /**
         * Save lost baskets to be send in Send table.
         */
	    //$locale = Mage::app()->getLocale()->getLocale();
		$stores = $this->_helper->getStores();
	    foreach ($stores as $store) {
            $storeId = $store->getId();
		    if ($mode == 'all' || $mode == 'customers') {
			    /**
			     * Customers campaings
			     */
			    foreach ( $this->lostBasketCustomers as $num ) {
				    //customer enabled
				    if ( $this->_getLostBasketCustomerEnabled( $num, $storeId ) ) {
					    //number of the campaign use minutes
					    if ( $num == 1 ) {
						    //$from = Zend_Date::now( $locale )->subMinute( $this->_getLostBasketCustomerInterval( $num, $storeId ) );
							//@todo get the localized time
						    $minutes = $this->_getLostBasketCustomerInterval( $num, $storeId );
						    $interval = new \DateInterval("PT" . $minutes . "M");

					    } else {
						    $hours = (int)$this->_getLostBasketCustomerInterval( $num, $storeId );
						    $interval = new \DateInterval("PT" . $hours . "H");
					    }
					    $fromTime = new \DateTime();
					    $fromTime->sub($interval);
					    $toTime = clone $fromTime;

					    $fromTime->sub(new \DateInterval("PT5M"));

					    //format time
					    $fromDate = $this->dateTime->formatDate($fromTime->getTimestamp());
					    $toDate = $this->dateTime->formatDate($toTime->getTimestamp());

					    //active quotes
					    $quoteCollection = $this->_getStoreQuotes( $fromDate, $toDate, $guest = false, $storeId );

					    if ( $quoteCollection->getSize() ) {
					    }
					    $this->_helper->log( 'Customer lost baskets : ' . $num . ', from : ' . $fromDate . ' ,to ' . $toDate);

					    //campaign id for customers
					    $campaignId = $this->_getLostBasketCustomerCampaignId( $num, $storeId );
					    foreach ( $quoteCollection as $quote ) {

						    $email        = $quote->getCustomerEmail();
						    $websiteId    = $store->getWebsiteId();
						    $quoteId      = $quote->getId();
						    //api - set the last quote id for customer
						    $this->_helper->updateLastQuoteId($quoteId, $email, $websiteId);

                            //@todo get the most expinsive item, change the logic by not looping
                            $items = $quote->getAllItems();
                            $mostExpensiveItem = false;
                            foreach ($items as $item) {
                                if ($mostExpensiveItem == false)
                                    $mostExpensiveItem = $item;
                                elseif ($item->getPrice() > $mostExpensiveItem->getPrice())
                                    $mostExpensiveItem = $item;
                            }
						    //api-send the most expensive product for abandoned cart
                            if ($mostExpensiveItem)
                                $this->_helper->updateAbandonedProductName($mostExpensiveItem->getName(), $email, $websiteId);

						    //send email only if the interval limit passed, no emails during this interval
						    $intervalLimit = $this->_checkCustomerCartLimit( $email, $storeId );
						    //no campign found for interval pass
                            if (!$intervalLimit) {

							    //save lost basket for sending
							    $this->_objectManager->create('Dotdigitalgroup\Email\Model\Campaign')
								    ->setEmail( $email )
								    ->setCustomerId( $quote->getCustomerId() )
								    ->setEventName( 'Lost Basket' )
							        ->setQuoteId($quoteId)
								    ->setMessage('Abandoned Cart :' . $num)
								    ->setCampaignId( $campaignId )
								    ->setStoreId( $storeId )
								    ->setWebsiteId($websiteId)
								    ->setIsSent( null )->save();
						    }
					    }
				    }

			    }
		    }
		    if ($mode == 'all' || $mode == 'guests') {
			    /**
			     * Guests campaigns
			     */
			    foreach ( $this->lostBasketGuests as $num ) {
				    if ( $this->_getLostBasketGuestEnabled( $num, $storeId ) ) {
					    if ( $num == 1 ) {
						    //$from = Zend_Date::now( $locale )->subMinute( $this->_getLostBasketCustomerInterval( $num, $storeId ) );
						    //@todo get the localized time
						    $minutes = $this->_getLostBasketGuestIterval( $num, $storeId );
						    $interval = new \DateInterval("PT" . $minutes . "M");
					    } else {
						    $hours = $this->_getLostBasketGuestIterval( $num, $storeId );
						    $interval = new \DateInterval("P" . $hours . "H");
					    }
					    $fromTime = new \DateTime();
					    $fromTime->sub($interval);
					    $toTime = clone $fromTime;
					    $fromTime->sub(new \DateInterval("PT5M"));

					    //format time
					    $fromDate = $this->dateTime->formatDate($fromTime->getTimestamp());
					    $toDate = $this->dateTime->formatDate($toTime->getTimestamp());

					    //active guest quotes
					    $quoteCollection = $this->_getStoreQuotes( $fromDate, $toDate, $guest = true, $storeId );

					    if ( $quoteCollection->getSize() ) {
						    $this->_helper->log( 'Guest lost baskets : ' . $num . ', from : ' . $fromDate . ' ,to : ' . $toDate );
					    }
					    $guestCampaignId = $this->_getLostBasketGuestCampaignId( $num, $storeId );
					    foreach ( $quoteCollection as $quote ) {
						    $email        = $quote->getCustomerEmail();
						    $websiteId    = $store->getWebsiteId();
						    $quoteId      = $quote->getId();
						    // upate last quote id for the contact
						    $this->_helper->updateLastQuoteId($quoteId, $email, $websiteId);
                            // update abandoned product name for contact
                            $items = $quote->getAllItems();
                            $mostExpensiveItem = false;
                            foreach ($items as $item) {
                                if ($mostExpensiveItem == false)
                                    $mostExpensiveItem = $item;
                                elseif ($item->getPrice() > $mostExpensiveItem->getPrice())
                                    $mostExpensiveItem = $item;
                            }
						    //api- set the most expensive product to datafield
                            if ($mostExpensiveItem)
                                $this->_helper->updateAbandonedProductName($mostExpensiveItem->getName(), $email, $websiteId);

						    //send email only if the interval limit passed, no emails during this interval
						    $campignFound = $this->_checkCustomerCartLimit( $email, $storeId );

						    //no campign found for interval pass
                            if (!$campignFound) {
							    //save lost basket for sending
							    $this->_objectManager->create('Dotdigitalgroup\Email\Model\Campaign')
								    ->setEmail( $email )
								    ->setEventName( 'Lost Basket' )
								    ->setQuoteId($quoteId)
								    ->setCheckoutMethod( 'Guest' )
								    ->setMessage('Guest Abandoned Cart : ' . $num)
								    ->setCampaignId( $guestCampaignId )
								    ->setStoreId( $storeId )
								    ->setWebsiteId($websiteId)
								    ->setIsSent( null )->save();
						    }
					    }
				    }
			    }
		    }
        }
    }

    private function _getLostBasketCustomerCampaignId($num, $storeId)
    {
	    return $this->scopeConfig->getValue(constant('self::XML_PATH_LOSTBASKET_CUSTOMER_CAMPAIGN_' . $num),
		    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
		    $storeId
	    );
    }
    private function _getLostBasketGuestCampaignId($num, $storeId)
    {
	    return $this->scopeConfig->getValue(constant('self::XML_PATH_LOSTBASKET_GUEST_CAMPAIGN_' . $num),
		    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
		    $storeId
	    );
    }

    private function _getLostBasketCustomerInterval($num, $storeId)
    {
	    return $this->scopeConfig->getValue(constant('self::XML_PATH_LOSTBASKET_CUSTOMER_INTERVAL_' . $num),
		    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
		    $storeId
	    );
    }

    private function _getLostBasketGuestIterval($num, $storeId)
    {
	    return $this->scopeConfig->getValue(constant('self::XML_PATH_LOSTBASKET_GUEST_INTERVAL_' . $num),
		    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
		    $storeId
	    );
    }

    protected function _getLostBasketCustomerEnabled($num, $storeId)
    {
	    return $this->scopeConfig->getValue(constant('self::XML_PATH_LOSTBASKET_CUSTOMER_ENABLED_' . $num),
		    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
		    $storeId
	    );
    }

    protected function _getLostBasketGuestEnabled($num, $storeId)
    {
	    return $this->scopeConfig->getValue(constant('self::XML_PATH_LOSTBASKET_GUEST_ENABLED_' . $num),
		    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
	        $storeId
	    );
    }

	/**
	 * @param null $from
	 * @param null $to
	 * @param bool|false $guest
	 * @param int $storeId
	 *
	 * @return $this
	 */
    private function _getStoreQuotes($from = null, $to = null, $guest = false, $storeId = 0)
    {
	    $updated = array(
            'from' => $from,
            'to' => $to,
            'date' => true);
	    //@todo reset the select is making the sql empty,
	    //$this->quoteCollection->getSelect()->reset();

        $salesCollection = $this->quoteCollection
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('items_count', array('gt' => 0))
            ->addFieldToFilter('customer_email', array('neq' => ''))
            ->addFieldToFilter('store_id', $storeId)
;//->addFieldToFilter('updated_at', $updated);
        //guests
	    if ($guest) {
	        $salesCollection->addFieldToFilter( 'main_table.customer_id', array( 'null' => true ) );
        } else {
		    //customers
	        $salesCollection->addFieldToFilter( 'main_table.customer_id', array( 'notnull' => true ) );
        }

        //process rules on collection
	    $ruleModel = $this->_objectManager->create('Dotdigitalgroup\Email\Model\Rules');
        $websiteId = $this->_storeManager->getStore($storeId)->getWebsiteId();
        $salesCollection = $ruleModel->process(
            $salesCollection, \Dotdigitalgroup\Email\Model\Rules::ABANDONED, $websiteId
        );

	    return $salesCollection;
    }

	/**
	 * Check customer campaign that was sent by a limit from config.
	 * Return false for any found for this period.
	 *
	 * @param $email
	 * @param $storeId
	 *
	 * @return bool
	 */
	private function _checkCustomerCartLimit($email, $storeId) {

		$cartLimit = $this->scopeConfig->getValue(\Dotdigitalgroup\Email\Helper\Config::XML_PATH_CONNECTOR_ABANDONED_CART_LIMIT,
			\Magento\Store\Model\ScopeInterface::SCOPE_STORE,
			$storeId);
		//@todo get locale
		//$locale = Mage::app()->getLocale()->getLocale();

		//no limit is set skip
		if (! $cartLimit)
			return false;

		//time diff
		//$to = Zend_Date::now($locale);
		//$from = Zend_Date::now($locale)->subHour($cartLimit);

		$fromTime = new \DateTime();
		$interval = new \DateInterval('P' . $cartLimit . 'H');
		$fromTime->sub($interval);
		$toTime = new \DateTime();
		$fromDate = $this->dateTime->formatDate($fromTime->getTimestamp());
		$toDate = $this->dateTime->formatDate($toTime->getTimestamp());
		$updated = array(
			'from' => $fromDate,
			'to' => $toDate,
			'date' => true
		);

		//number of campigns during this time
		$campaignLimit = $this->_objectManager->create('Dotdigitalgroup\Email\Model\Campaign')->getCollection()
			->addFieldToFilter('email', $email)
			->addFieldToFilter('event_name', 'Lost Basket')
			->addFieldToFilter('sent_at', $updated)
			->count();

		if ($campaignLimit)
			return true;

		return false;
	}
}