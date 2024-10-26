<?php

namespace OneCRM\Portal\API;

use OneCRM\APIClient\Client as APIClient;

class Client extends APIClient
{
	/**
	 * Creates an instanse of Subscription class to work with Subscription data normalized to 1CRM db structure.
	 *
	 * NOTE: some objects are backed by 1CRM db, others by PaymentProcessor (ie payment methods)
	 * NOTE: most objects are created/modified by notifying PaymentProcessor of changes
	 *
	 * 1CRM Objects:  (Global, restricted fields)
	 *      Subscription, Plan, Addon, Coupon, PaymentProcessor
	 * 1CRM Objects:  (Heirarchy, restricted fields)
	 *      Invoice, Payment
	 *
	 * Derived Objects (Heirarchy, read-write)
	 *      Customer (Contact + Account related to PP)
	 * Derived Objects (Heirarchy, read-only)
	 *      Invoice (Invoice + InvoiceLineGroup + InvoiceLine(s) + InvoiceAdjustment(s) + invoices_payments)
	 *
	 * PaymentProcessor Objects (heirarchy, read-only):
	 *      PaymentMethod
	 *
	 * @param string $model_name ex. Account
	 * @return Subscription
	 * @throws \OneCRM\APIClient\Error
	 */
	
	public function subscription($object_name, $processor)
	{
		return new Subscription($this, $object_name, $processor);
	}

}
