<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Tests\Controller;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Mateusz Zalewski <mateusz.zalewski@lakion.com>
 */
final class CheckoutAddressingApiTest extends CheckoutApiTestCase
{
    /**
     * @test
     */
    public function it_denies_order_addressing_for_non_authenticated_user()
    {
        $this->client->request('PUT', '/api/v1/checkouts/addressing/1');

        $response = $this->client->getResponse();
        $this->assertResponse($response, 'authentication/access_denied_response', Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_address_unexisting_order()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');

        $this->client->request('PUT', '/api/v1/checkouts/addressing/1', [], [], static::$authorizedHeaderWithContentType);

        $response = $this->client->getResponse();
        $this->assertResponseCode($response, Response::HTTP_NOT_FOUND);
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_address_order_without_specifying_customer_email()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');
        $checkoutData = $this->loadFixturesFromFile('resources/checkout.yml');

        /** @var OrderInterface $cart */
        $cart = $checkoutData['order1'];

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType);

        $response = $this->client->getResponse();
        $this->assertResponse($response, 'checkout/addressing_invalid_customer', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_address_order_without_specifying_shipping_address()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');
        $checkoutData = $this->loadFixturesFromFile('resources/checkout.yml');

        /** @var OrderInterface $cart */
        $cart = $checkoutData['order1'];

        $data =
<<<EOT
        {
            "different_billing_address": false,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $data);

        $response = $this->client->getResponse();
        $this->assertResponse($response, 'checkout/addressing_validation_failed_shipping_address', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @test
     */
    public function it_allows_to_address_order_with_the_same_shipping_and_billing_address()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');
        $this->loadFixturesFromFile('resources/countries.yml');
        $checkoutData = $this->loadFixturesFromFile('resources/checkout.yml');

        /** @var OrderInterface $cart */
        $cart = $checkoutData['order1'];

        $data =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Hieronim",
                "last_name": "Bosch",
                "street": "Surrealism St.",
                "country_code": "NL",
                "city": "’s-Hertogenbosch",
                "postcode": "99-999"
            },
            "different_billing_address": false,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $data);

        $response = $this->client->getResponse();
        $this->assertResponseCode($response, Response::HTTP_NO_CONTENT);
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_address_order_with_different_addresses_if_billing_address_is_not_defined()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');
        $this->loadFixturesFromFile('resources/countries.yml');
        $this->loadFixturesFromFile('resources/customers.yml');
        $checkoutData = $this->loadFixturesFromFile('resources/checkout.yml');

        /** @var OrderInterface $cart */
        $cart = $checkoutData['order1'];

        $data =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Hieronim",
                "last_name": "Bosch",
                "street": "Surrealism St.",
                "country_code": "NL",
                "city": "’s-Hertogenbosch",
                "postcode": "99-999"
            },
            "different_billing_address": true,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $data);

        $response = $this->client->getResponse();
        $this->assertResponse($response, 'checkout/addressing_validation_failed_billing_address', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @test
     */
    public function it_allows_to_address_order_with_different_shipping_and_billing_address()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');
        $this->loadFixturesFromFile('resources/countries.yml');
        $checkoutData = $this->loadFixturesFromFile('resources/checkout.yml');

        /** @var OrderInterface $cart */
        $cart = $checkoutData['order1'];

        $data =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Hieronim",
                "last_name": "Bosch",
                "street": "Surrealism St.",
                "country_code": "NL",
                "city": "’s-Hertogenbosch",
                "postcode": "99-999"
            },
            "billing_address": {
                "first_name": "Vincent",
                "last_name": "van Gogh",
                "street": "Post-Impressionism St.",
                "country_code": "NL",
                "city": "Groot Zundert",
                "postcode": "88-888"
            },
            "different_billing_address": true,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $data);

        $response = $this->client->getResponse();
        $this->assertResponseCode($response, Response::HTTP_NO_CONTENT);

        $this->client->request('GET', $this->getCheckoutSummaryUrl($cart), [], [], static::$authorizedHeaderWithAccept);

        $response = $this->client->getResponse();
        $this->assertResponse($response, 'checkout/addressed_order_response');
    }

    /**
     * @test
     */
    public function it_allows_to_change_order_address_after_the_order_has_already_been_addressed()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');
        $this->loadFixturesFromFile('resources/countries.yml');
        $checkoutData = $this->loadFixturesFromFile('resources/checkout.yml');

        /** @var OrderInterface $cart */
        $cart = $checkoutData['order1'];

        $data =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Vincent",
                "last_name": "van Gogh",
                "street": "Post-Impressionism St.",
                "country_code": "NL",
                "city": "Groot Zundert",
                "postcode": "88-888"
            },
            "different_billing_address": false,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $data);

        $newData =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Hieronim",
                "last_name": "Bosch",
                "street": "Surrealism St.",
                "country_code": "NL",
                "city": "’s-Hertogenbosch",
                "postcode": "99-999"
            },
            "different_billing_address": false,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $newData);

        $response = $this->client->getResponse();
        $this->assertResponseCode($response, Response::HTTP_NO_CONTENT);
    }

    /**
     * @test
     */
    public function it_allows_to_change_order_address_after_selecting_shipping_method()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');
        $this->loadFixturesFromFile('resources/countries.yml');
        $checkoutData = $this->loadFixturesFromFile('resources/checkout.yml');

        /** @var OrderInterface $cart */
        $cart = $checkoutData['order1'];
        /** @var ShippingMethodInterface $shippingMethod */
        $shippingMethod = $checkoutData['ups'];

        $addressData =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Vincent",
                "last_name": "van Gogh",
                "street": "Post-Impressionism St.",
                "country_code": "NL",
                "city": "Groot Zundert",
                "postcode": "88-888"
            },
            "different_billing_address": false,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $addressData);

        $this->selectOrderShippingMethod($cart, $shippingMethod);

        $newAddressData =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Hieronim",
                "last_name": "Bosch",
                "street": "Surrealism St.",
                "country_code": "NL",
                "city": "’s-Hertogenbosch",
                "postcode": "99-999"
            },
            "different_billing_address": false,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $newAddressData);

        $response = $this->client->getResponse();
        $this->assertResponseCode($response, Response::HTTP_NO_CONTENT);
    }

    /**
     * @test
     */
    public function it_allows_to_change_order_address_after_selecting_payment_method()
    {
        $this->loadFixturesFromFile('authentication/api_administrator.yml');
        $this->loadFixturesFromFile('resources/countries.yml');
        $checkoutData = $this->loadFixturesFromFile('resources/checkout.yml');

        /** @var OrderInterface $cart */
        $cart = $checkoutData['order1'];
        /** @var ShippingMethodInterface $shippingMethod */
        $shippingMethod = $checkoutData['ups'];
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $checkoutData['cash_on_delivery'];

        $addressData =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Vincent",
                "last_name": "van Gogh",
                "street": "Post-Impressionism St.",
                "country_code": "NL",
                "city": "Groot Zundert",
                "postcode": "88-888"
            },
            "different_billing_address": false,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $addressData);

        $this->selectOrderShippingMethod($cart, $shippingMethod);
        $this->selectOrderPaymentMethod($cart, $paymentMethod);

        $newAddressData =
<<<EOT
        {
            "shipping_address": {
                "first_name": "Hieronim",
                "last_name": "Bosch",
                "street": "Surrealism St.",
                "country_code": "NL",
                "city": "’s-Hertogenbosch",
                "postcode": "99-999"
            },
            "different_billing_address": false,
            "customer": {
                "email": "john@doe.com"
            }
        }
EOT;

        $this->client->request('PUT', $this->getAddressingUrl($cart), [], [], static::$authorizedHeaderWithContentType, $newAddressData);

        $response = $this->client->getResponse();
        $this->assertResponseCode($response, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param OrderInterface $cart
     *
     * @return string
     */
    private function getAddressingUrl(OrderInterface $cart)
    {
        return sprintf('/api/v1/checkouts/addressing/%d', $cart->getId());
    }
}
