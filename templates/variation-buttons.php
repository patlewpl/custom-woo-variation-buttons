<?php
/**
 * Variation buttons widget markup.
 *
 * Override by copying this file to yourtheme/custom-wvb/variation-buttons.php
 *
 * @var array $cwvb {
 *     @type string $instance_id  Unique wrapper id.
 *     @type array  $attributes   One entry per attribute: name, label, type, options.
 *     @type array  $variations   Variation data for the script.
 *     @type array  $order        Attribute keys, in DOM order.
 *     @type int    $quantity     Initial quantity.
 *     @type string $button_text  Add-to-cart button label.
 *     @type array  $config       Runtime config for the script.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<div
    id="<?php echo esc_attr( $cwvb['instance_id'] ); ?>"
    class="custom-wvb"
    data-config="<?php echo esc_attr( wp_json_encode( $cwvb['config'] ) ); ?>"
>
    <div class="custom-wvb__attributes">
        <?php foreach ( $cwvb['attributes'] as $attribute ) : ?>
            <div
                class="custom-wvb__attribute"
                data-attribute-name="<?php echo esc_attr( $attribute['name'] ); ?>"
                data-attribute-type="<?php echo esc_attr( $attribute['type'] ); ?>"
            >
                <div class="custom-wvb__label">
                    <?php echo esc_html( $attribute['label'] ); ?>
                </div>

                <?php if ( 'select' === $attribute['type'] ) : ?>
                    <select
                        class="custom-wvb__select"
                        name="<?php echo esc_attr( $attribute['name'] ); ?>"
                    >
                        <option value="">
                            <?php echo esc_html( sprintf( 'Wybierz: %s', $attribute['label'] ) ); ?>
                        </option>

                        <?php foreach ( $attribute['options'] as $option ) : ?>
                            <option value="<?php echo esc_attr( $option['value'] ); ?>">
                                <?php echo esc_html( $option['label'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <div class="custom-wvb__options" role="group" aria-label="<?php echo esc_attr( $attribute['label'] ); ?>">
                        <?php foreach ( $attribute['options'] as $option ) : ?>
                            <button
                                type="button"
                                class="custom-wvb__option"
                                data-value="<?php echo esc_attr( $option['value'] ); ?>"
                                aria-pressed="false"
                            >
                                <?php echo esc_html( $option['label'] ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="custom-wvb__result" aria-live="polite">
        <div class="custom-wvb__price"></div>
        <div class="custom-wvb__message"></div>
    </div>

    <div class="custom-wvb__purchase">
        <label class="custom-wvb__quantity">
            <span>Ilość</span>
            <input
                type="number"
                class="custom-wvb__qty"
                value="<?php echo esc_attr( $cwvb['quantity'] ); ?>"
                min="1"
                step="1"
            >
        </label>

        <button
            type="button"
            class="custom-wvb__add"
            disabled
        >
            <?php echo esc_html( $cwvb['button_text'] ); ?>
        </button>
    </div>

    <script type="application/json" class="custom-wvb__data">
        <?php
        /*
         * Only the data the browser actually reads. Labels and options are
         * already in the markup above. JSON_HEX_TAG keeps HTML coming from
         * price_html from breaking out of this script element.
         */
        echo wp_json_encode(
            array(
                'order'      => $cwvb['order'],
                'variations' => $cwvb['variations'],
            ),
            JSON_HEX_TAG | JSON_HEX_AMP
        );
        ?>
    </script>
</div>