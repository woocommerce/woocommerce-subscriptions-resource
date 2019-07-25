# WooCommerce Subscriptions Resource [![Build Status](https://travis-ci.org/Prospress/woocommerce-subscriptions-resource.svg?branch=master)](https://travis-ci.org/Prospress/woocommerce-subscriptions-resource) [![license: GPL v2](https://img.shields.io/badge/license-GPLv2-blue.svg)](http://opensource.org/licenses/GPL-2.0)


A library to track and prorate payments for a subscription using WooCommerce Subscriptions based on the status of an external resource.

A "_resource_" is deliberately generic to allow for proration of anything. For example, it is implemented on [Robot Ninja](http://robotninja.com/) to prorate payments for stores similar to [Slack's billing](https://get.slack.help/hc/en-us/articles/218915077). In this case, the _store_ is the resource. But other resources could be a seat in a club membership, a video library for an education site, or a server for a web hosting service.

Resources can be pre-paid or post-paid. They can be prorated to daily rates, or charged in full for each billing period.

## Proration Behaviour

By default, proration for resources works very similar to [Slack's billing](https://get.slack.help/hc/en-us/articles/218915077), because we thought it was cool, and very fair, so wanted to use it for Robot Ninja.

Specifically, any changes to the number of active resources will be reflected in the next renewal order, prorated daily.

Here’s an example:

> Let’s suppose your customer is on a subscription which costs $8 per resource per month. You add a new resource 10 days into your billing period, leaving 20 days remaining in the month.

> The prorated renewal cost is calculated by dividing the cost per resource ($8) by the number of days in the month (30) and multiplying it by the number of days remaining (20), which gives the prorated cost for the remainder of that billing period: $5.33 

> That amount will be set as a line item for that resource 

If your subscription bills annually, it works the same way. The prorated cost for the prior year will be calculated and billed at the next renewal.

## Usage

The code in this library will take care of creating and linking a resource. All your code needs to do is tell it when to create or update a resource and the IDs of objects to link it to.

This can be done with the following hooks:

* `wcsr_create_resource`
* `wcsr_activate_resource`  
* `wcsr_deactivate_resource`

### Create a Resource

To create a new resource, trigger the `wcsr_create_resource` hook via `do_action()` and pass the following parameters:

1. `$status` (`string`):  the status for the resource at the time of creation. Should be either 'active' or 'inactive', unless using a custom resource class which can handle other statuses.
1. `$external_id` (`int`):  the ID of the external object to link this resource to. For example, to link it to a _store_ on [Robot Ninja](http://robotninja.com/), the store's ID is passed as the `$external_id`.
1. `$subscription_id` (`int`):  the ID of the subscription in WooCommerce to link this resource to.
1. `$args` (`array`): A set of params to customise the behaviour of the resource, especially for proration and pre-pay vs. post pay. Default value: `array ( 'is_pre_paid'  => true, 'is_prorated'  => false )`.

#### Resource Creation Example  Code

To create a active resource linked to a store with ID 23 and subscription with ID 159, the following code can be used: 

```
do_action( 'wcsr_create_resource', 'active', 23, 159 );
```

### Activate a Resource

To record the activation of an existing resource, trigger the `wcsr_activate_resource` hook via `do_action()` and pass the following parameter:

1. `$external_id` (`int`):  the ID of the external object to link this resource to. For example, to link it to a _store_ on [Robot Ninja](http://robotninja.com/), the store's ID is passed as the `$external_id`.

This will record the resource's activation timestamp against the resource so that it can later be used for prorating that period's payment, if required.

#### Resource Activation Example  Code

To active a resource linked to a store with ID 23, the following code can be used: 

```
do_action( 'wcsr_activate_resource', 23 );
```

### Deactivate a Resource

To record the deactivation of an existing resource, trigger the `wcsr_deactivate_resource` hook via `do_action()` and pass the following parameter:

1. `$external_id` (`int`):  the ID of the external object to link this resource to. For example, to link it to a _store_ on [Robot Ninja](http://robotninja.com/), the store's ID is passed as the `$external_id`.

This will record the resource's deactivation timestamp against the resource so that it can later be used for prorating that period's payment, if required.

#### Resource Deactivation Example  Code

To deactive a resource linked to a store with ID 23, the following code can be used: 

```
do_action( 'wcsr_deactivate_resource', 23 );
```

### Add custom line items or modify prorated totals

If you want to customize the prorated amounts, or apply other logic to orders that have been prorated, like adding other line items, the `'wcsr_after_renewal_order_prorated'` hook is triggered after a renewal order's totals have been prorated.

For example, we use this on Robot Ninja to implmement a minimum amount for renewal orders of $9 by adding custom fee line items to orders with a prorated total of less than $9.

Callbacks on the `wcsr_after_renewal_order_prorated` filter receive:

1. an `WC_Order` object representing the prorated renewal order as the first param.
2. an array of resource IDs for the matching subscription as the 2nd param.

> Note: this filter is only trigger for orders with prorated amounts, making it easier to use then existing, more generic hooks, like `'wcs_renewal_order_created'`.

#### Custom Resource Line Item Name Example

The following snippet shows code similar to that used on Robot Ninja to enforce a minimum monthly access fee of $9. If the prorated renewal order is less than $9 based on the resource usage during the prior month, then a new fee line item is added to the renewal order for difference.

```php
public function eg_add_minimum_fee( $renewal_order, $resource_ids ) {

	if ( $renewal_order->get_total() < 9 ) {

		$fee_item = new WC_Order_Item_Fee();

		$fee_item->set_props( array(
			'name'      => 'Robot Ninja Gap Fee for Monthly Minimum',
			'tax_class' => '',
			'total'     => wc_format_decimal( 9.00 - $renewal_order->get_total() ),
			'total_tax' => '',
			'taxes'     => array(
				'total' => 0,
			),
			'order_id'  => $renewal_order->get_id(),
		) );

		$fee_item->save();

		$renewal_order->add_item( $fee_item );

		$renewal_order->calculate_totals(); // also saves the order
	}

	return $renewal_order;
}
add_filter( 'wcsr_after_renewal_order_prorated', ''eg_add_minimum_fee, 10, 2 );_
```

### Link a Resource to Line Item Name on Renewal Order

Each resource will be set as a separate line item on the renewal orders (using the correct product IDs to make sure reports are accurate).

This has the added advantage of being able to identify each resource on each line item by filtering the line item name for that resource.

To do this, add a callback to the `wcsr_renewal_line_item_name` filter. You can use the `WCSR_Resource` object passed to your callback to derive information about the resource.

#### Custom Resource Line Item Name Example

To add the _store_ on [Robot Ninja](http://robotninja.com/) for a resource linked to a store with ID 23, the following code can be used: 

```
function eg_add_store_to_line_item( $line_item_name, $resource, $line_item, $days_active ) {

	// Get the Robot Ninja Store
	$store = get_store( $resource->get_external_id() );

	$line_item_name = sprintf( '%s (%s) %s usage for %d days.', $store['name'], $store['url'], $line_item->get_name(), $days_active );

	return $line_item_name;
}
add_filter( 'wcsr_renewal_line_item_name', 'eg_add_store_to_line_item', 10, 4 );
```

## FAQ

### Do the subscription's line item totals display updated amounts each day to account for proration?

No. For now, on the customer facing **My Account > View Subscription** and admin facing **WooCommerce > Edit Subscription** screens, the subscription line item totals will only display whatever line items were set on it at the time of sign-up.

These totals are then used at the time of renewal to determine the prorated amount.

The renewal order's line item totals will then display the prorated amounts for the prior billing period.

### Can a resource be linked to a specific line item on the subscription?

No. For now, it can only be linked to a subscription as a whole, and whatever product line items are set on that subscription will be prorated.

This makes is simpler to keep resources and subscriptions in sync, because it means your customers can switch line items on a subscription without the resource needing to be updated.

It also makes it possible for multiple line items to be prorated for a given resource. For example, you might rent a VPS Server with an optional 1TB Storage addon. These can be included as separate line items on the subscription and renewal order, which helps keep track of revenue from each. But both can be prorated based on the resource's usage.

### How is the number of days active determined?

If a resource is active for more than 1 second on a given day, it will be considered to have been "active" on that day, and a prorated charge for that day will be included on the renewal.

When prorating renewal amounts, the  resource object will check for the number of days the resource was active for more than one second between the date the subscription's last order was _paid_ (i.e. not created) and when the renewal was was created. This matches Subscriptions default next payment calculations, which base the next payment date based on the last payment date.

If the subscription has no date for the last paid order, then the from date will attempt to use the last order's creation date, and if that does not exist, the subscription's creation creation.

If you are continuing to provide access to the resource even between when a payment is due and when it is processed, by using a plugin like [WooCommerce Subscriptions - Preserve Billing Schedule](https://github.com/Prospress/woocommerce-subscriptions-preserve-billing-schedule), this default behaviour will result in incorrectly prorated amounts. You would instead wish for proration to always be based on the date the last order was created.

To achieve this, you can use the `'wcsr_renewal_proration_from_timetamp'` filter with a callback like this:

```
function eg_use_last_order_date_created_for_resource_proration( $from_timestamp, $subscription, $renewal_order, $resource ) {

	$from_timestamp = eg_function_to_get_second_last_orders_creation_time();

	return $from_timestamp;
}
add_filter( 'wcsr_renewal_proration_from_timetamp', 'eg_use_last_order_date_created_for_resource_proration', 10, 4 );
```

### Are shipping, fee or other line items prorated?

No. Only product line item amounts will be prorated.

## Installation

This isn't a plugin, installing it as a plugin won't do anything.

It's a class that can be extended from your code to implement your own resource type.

You can include it via Git Subtree merge, or using Composer.

**Requires WooCommerce 3.0 or newer.**

## Reporting Issues

If you find an problem or would like to request this library be extended, please [open a new Issue](https://github.com/Prospress/woocommerce-subscriptions-resource/issues/new).

---

<p align="center">
	<a href="https://prospress.com/">
		<img src="https://cloud.githubusercontent.com/assets/235523/11986380/bb6a0958-a983-11e5-8e9b-b9781d37c64a.png" width="160">
	</a>
</p>