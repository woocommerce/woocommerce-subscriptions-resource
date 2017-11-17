#!/usr/bin/env bash
# usage: travis.sh before|after

set -e

say() {
  echo -e "$1"
}

if [ $1 == 'before' ]; then

	mkdir -p "../woocommerce-subscriptions"
	# place a copy of woocommerce subscriptions where the unit tests etc. expect it to be - needs to be a repo with the test dir
	git clone https://$GITHUB_TOKEN@github.com/Prospress/woocommerce-subscriptions.git "../woocommerce-subscriptions" -b $WCS_VERSION

	say "WooCommerce Subscriptions Installed"
fi
