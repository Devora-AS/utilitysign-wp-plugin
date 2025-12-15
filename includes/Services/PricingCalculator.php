<?php
namespace UtilitySign\Services;

use UtilitySign\Traits\Base;

/**
 * Pricing Calculator Service
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class PricingCalculator {
    use Base;

    /**
     * Calculate pricing for a product
     * 
     * @since 1.0.0
     * @param array $params
     * @return array|false
     */
    public function calculate_pricing($params) {
        try {
            $product_id = $params['product_id'];
            $quantity = $params['quantity'];
            $variation_id = $params['variation_id'] ?? null;
            $supplier_id = $params['supplier_id'] ?? null;
            $customer_type = $params['customer_type'] ?? 'standard';
            $discount_code = $params['discount_code'] ?? null;

            // Get base product price
            $base_price = $this->get_base_price($product_id, $variation_id);
            if (!$base_price) {
                return false;
            }

            // Calculate quantity pricing
            $quantity_price = $this->calculate_quantity_pricing($product_id, $base_price, $quantity);

            // Calculate volume discounts
            $volume_discount = $this->calculate_volume_discount($product_id, $quantity_price, $quantity);

            // Calculate tier pricing
            $tier_price = $this->calculate_tier_pricing($product_id, $quantity_price, $quantity);

            // Calculate supplier discounts
            $supplier_discount = $this->calculate_supplier_discount($supplier_id, $tier_price);

            // Calculate customer type discounts
            $customer_discount = $this->calculate_customer_discount($customer_type, $supplier_discount);

            // Calculate discount code
            $discount_code_amount = $this->calculate_discount_code($discount_code, $customer_discount);

            // Calculate final pricing
            $subtotal = $discount_code_amount;
            $tax_rate = $this->get_tax_rate();
            $tax_amount = $subtotal * $tax_rate;
            $total = $subtotal + $tax_amount;

            return [
                'base_price' => $base_price,
                'quantity_price' => $quantity_price,
                'volume_discount' => $volume_discount,
                'tier_price' => $tier_price,
                'supplier_discount' => $supplier_discount,
                'customer_discount' => $customer_discount,
                'discount_code_amount' => $discount_code_amount,
                'subtotal' => $subtotal,
                'tax_rate' => $tax_rate,
                'tax_amount' => $tax_amount,
                'total' => $total,
                'currency' => $this->get_currency(),
                'breakdown' => $this->get_pricing_breakdown($params)
            ];

        } catch (\Exception $e) {
            error_log('Pricing calculation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get base price for product
     * 
     * @since 1.0.0
     * @param int $product_id
     * @param int|null $variation_id
     * @return float|false
     */
    private function get_base_price($product_id, $variation_id = null) {
        if ($variation_id) {
            $variation_price = get_post_meta($variation_id, '_variation_price', true);
            if ($variation_price) {
                return floatval($variation_price);
            }
        }

        $product_price = get_post_meta($product_id, '_product_price', true);
        if ($product_price) {
            return floatval($product_price);
        }

        return false;
    }

    /**
     * Calculate quantity pricing
     * 
     * @since 1.0.0
     * @param int $product_id
     * @param float $base_price
     * @param int $quantity
     * @return float
     */
    private function calculate_quantity_pricing($product_id, $base_price, $quantity) {
        // Get quantity pricing rules
        $quantity_rules = get_post_meta($product_id, '_quantity_pricing', true);
        
        if (!$quantity_rules || !is_array($quantity_rules)) {
            return $base_price * $quantity;
        }

        // Find applicable quantity rule
        $applicable_rule = null;
        foreach ($quantity_rules as $rule) {
            if ($quantity >= $rule['min_quantity'] && $quantity <= $rule['max_quantity']) {
                $applicable_rule = $rule;
                break;
            }
        }

        if ($applicable_rule) {
            if ($applicable_rule['type'] === 'fixed') {
                return $applicable_rule['price'] * $quantity;
            } elseif ($applicable_rule['type'] === 'percentage') {
                return $base_price * $quantity * (1 - $applicable_rule['discount'] / 100);
            }
        }

        return $base_price * $quantity;
    }

    /**
     * Calculate volume discounts
     * 
     * @since 1.0.0
     * @param int $product_id
     * @param float $price
     * @param int $quantity
     * @return float
     */
    private function calculate_volume_discount($product_id, $price, $quantity) {
        $volume_discounts = get_post_meta($product_id, '_volume_discounts', true);
        
        if (!$volume_discounts || !is_array($volume_discounts)) {
            return $price;
        }

        $discount_amount = 0;
        foreach ($volume_discounts as $discount) {
            if ($quantity >= $discount['min_quantity']) {
                if ($discount['type'] === 'percentage') {
                    $discount_amount = $price * ($discount['discount'] / 100);
                } elseif ($discount['type'] === 'fixed') {
                    $discount_amount = $discount['discount'];
                }
            }
        }

        return $price - $discount_amount;
    }

    /**
     * Calculate tier pricing
     * 
     * @since 1.0.0
     * @param int $product_id
     * @param float $price
     * @param int $quantity
     * @return float
     */
    private function calculate_tier_pricing($product_id, $price, $quantity) {
        $tier_pricing = get_post_meta($product_id, '_tier_pricing', true);
        
        if (!$tier_pricing || !is_array($tier_pricing)) {
            return $price;
        }

        // Find applicable tier
        $applicable_tier = null;
        foreach ($tier_pricing as $tier) {
            if ($quantity >= $tier['min_quantity']) {
                $applicable_tier = $tier;
            }
        }

        if ($applicable_tier) {
            if ($applicable_tier['type'] === 'fixed') {
                return $applicable_tier['price'] * $quantity;
            } elseif ($applicable_tier['type'] === 'percentage') {
                return $price * (1 - $applicable_tier['discount'] / 100);
            }
        }

        return $price;
    }

    /**
     * Calculate supplier discounts
     * 
     * @since 1.0.0
     * @param int|null $supplier_id
     * @param float $price
     * @return float
     */
    private function calculate_supplier_discount($supplier_id, $price) {
        if (!$supplier_id) {
            return $price;
        }

        $supplier_discount = get_post_meta($supplier_id, '_supplier_discount', true);
        
        if (!$supplier_discount) {
            return $price;
        }

        if ($supplier_discount['type'] === 'percentage') {
            return $price * (1 - $supplier_discount['discount'] / 100);
        } elseif ($supplier_discount['type'] === 'fixed') {
            return max(0, $price - $supplier_discount['discount']);
        }

        return $price;
    }

    /**
     * Calculate customer type discounts
     * 
     * @since 1.0.0
     * @param string $customer_type
     * @param float $price
     * @return float
     */
    private function calculate_customer_discount($customer_type, $price) {
        $customer_discounts = get_option('utilitysign_customer_discounts', []);
        
        if (!isset($customer_discounts[$customer_type])) {
            return $price;
        }

        $discount = $customer_discounts[$customer_type];
        
        if ($discount['type'] === 'percentage') {
            return $price * (1 - $discount['discount'] / 100);
        } elseif ($discount['type'] === 'fixed') {
            return max(0, $price - $discount['discount']);
        }

        return $price;
    }

    /**
     * Calculate discount code
     * 
     * @since 1.0.0
     * @param string|null $discount_code
     * @param float $price
     * @return float
     */
    private function calculate_discount_code($discount_code, $price) {
        if (!$discount_code) {
            return $price;
        }

        $discount = get_option('utilitysign_discount_codes', []);
        
        if (!isset($discount[$discount_code])) {
            return $price;
        }

        $discount_data = $discount[$discount_code];
        
        // Check if discount is still valid
        if ($discount_data['expires'] && strtotime($discount_data['expires']) < time()) {
            return $price;
        }

        if ($discount_data['type'] === 'percentage') {
            return $price * (1 - $discount_data['discount'] / 100);
        } elseif ($discount_data['type'] === 'fixed') {
            return max(0, $price - $discount_data['discount']);
        }

        return $price;
    }

    /**
     * Get tax rate
     * 
     * @since 1.0.0
     * @return float
     */
    private function get_tax_rate() {
        return floatval(get_option('utilitysign_tax_rate', 0.25)); // 25% default
    }

    /**
     * Get currency
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_currency() {
        return get_option('utilitysign_currency', 'NOK');
    }

    /**
     * Get pricing breakdown
     * 
     * @since 1.0.0
     * @param array $params
     * @return array
     */
    private function get_pricing_breakdown($params) {
        return [
            'product_id' => $params['product_id'],
            'quantity' => $params['quantity'],
            'variation_id' => $params['variation_id'] ?? null,
            'supplier_id' => $params['supplier_id'] ?? null,
            'customer_type' => $params['customer_type'] ?? 'standard',
            'discount_code' => $params['discount_code'] ?? null,
            'calculated_at' => current_time('mysql')
        ];
    }

    /**
     * Get pricing variations for a product
     * 
     * @since 1.0.0
     * @param int $product_id
     * @return array
     */
    public function get_pricing_variations($product_id) {
        $variations = get_post_meta($product_id, '_product_variations', true);
        
        if (!$variations || !is_array($variations)) {
            return [];
        }

        $result = [];
        foreach ($variations as $variation) {
            $result[] = [
                'id' => $variation['id'],
                'name' => $variation['name'],
                'price' => $variation['price'],
                'description' => $variation['description'] ?? '',
                'available' => $variation['available'] ?? true
            ];
        }

        return $result;
    }

    /**
     * Get available discounts
     * 
     * @since 1.0.0
     * @param array $params
     * @return array
     */
    public function get_available_discounts($params) {
        $discounts = [];

        // Get product-specific discounts
        if (isset($params['product_id'])) {
            $product_discounts = get_post_meta($params['product_id'], '_product_discounts', true);
            if ($product_discounts && is_array($product_discounts)) {
                $discounts = array_merge($discounts, $product_discounts);
            }
        }

        // Get supplier-specific discounts
        if (isset($params['supplier_id'])) {
            $supplier_discounts = get_post_meta($params['supplier_id'], '_supplier_discounts', true);
            if ($supplier_discounts && is_array($supplier_discounts)) {
                $discounts = array_merge($discounts, $supplier_discounts);
            }
        }

        // Get global discounts
        $global_discounts = get_option('utilitysign_global_discounts', []);
        if ($global_discounts && is_array($global_discounts)) {
            $discounts = array_merge($discounts, $global_discounts);
        }

        return $discounts;
    }
}