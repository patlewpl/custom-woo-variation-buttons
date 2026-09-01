<?php
/**
 * Variation buttons widget markup.
 *
 * Override by copying this file to yourtheme/custom-wvb/variation-buttons.php
 *
 * @var array $cwvb {
 *     @type string $instance_id    Unique wrapper id.
 *     @type array  $attributes     One entry per attribute: name, label, type, options, step.
 *     @type array  $variations     Variation data for the script.
 *     @type array  $order          Attribute keys, in DOM order.
 *     @type int    $quantity       Initial quantity.
 *     @type string $button_text    Add-to-cart button label.
 *     @type bool   $show_quantity  Whether the quantity field is visible.
 *     @type string $price_prefix   Text shown to the left of the price.
 *     @type string $step_format    Step number format, {n} = number. Empty = no numbers.
 *     @type string $benefits_title Heading above the benefits list.
 *     @type array  $benefits       Benefit lines, already trimmed and filtered.
 *     @type string $benefits_marker Character in front of every benefit.
 *     @type string $benefits_note  Small print under the benefits list.
 *     @type array  $config         Runtime config for the script.
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
                    <?php if ( '' !== $cwvb['step_format'] ) : ?>
                        <span class="custom-wvb__step"><?php
                            echo esc_html( str_replace( '{n}', (string) $attribute['step'], $cwvb['step_format'] ) );
                        ?></span>
                    <?php endif; ?>
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

    <?php
    // Static package copy: the same for every variation, so it is not touched by
    // the script and stays visible before anything is selected.
    if ( '' !== $cwvb['benefits_title'] || ! empty( $cwvb['benefits'] ) || '' !== $cwvb['benefits_note'] ) :
        ?>
        <div class="custom-wvb__benefits">
            <?php if ( '' !== $cwvb['benefits_title'] ) : ?>
                <div class="custom-wvb__benefits-title">
                    <?php echo esc_html( $cwvb['benefits_title'] ); ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $cwvb['benefits'] ) ) : ?>
                <?php // role="list" because list-style:none drops list semantics in Safari. ?>
                <ul class="custom-wvb__benefits-list" role="list">
                    <?php foreach ( $cwvb['benefits'] as $benefit ) : ?>
                        <li class="custom-wvb__benefit">
                            <?php if ( '' !== $cwvb['benefits_marker'] ) : ?>
                                <?php // Decorative: a screen reader repeating "check" four times helps nobody. ?>
                                <span class="custom-wvb__benefit-marker" aria-hidden="true"><?php
                                    echo esc_html( $cwvb['benefits_marker'] );
                                ?></span>
                            <?php endif; ?>

                            <span class="custom-wvb__benefit-text"><?php echo esc_html( $benefit ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ( '' !== $cwvb['benefits_note'] ) : ?>
                <p class="custom-wvb__benefits-note">
                    <?php echo esc_html( $cwvb['benefits_note'] ); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="custom-wvb__result" aria-live="polite">
        <?php // Hidden until a variation resolves, so the prefix never shows on its own. ?>
        <div class="custom-wvb__price-row" hidden>
            <?php if ( '' !== $cwvb['price_prefix'] ) : ?>
                <span class="custom-wvb__price-prefix"><?php echo esc_html( $cwvb['price_prefix'] ); ?></span>
            <?php endif; ?>

            <div class="custom-wvb__price"></div>
        </div>

        <div class="custom-wvb__message"></div>
    </div>

    <div class="custom-wvb__purchase">
        <?php if ( $cwvb['show_quantity'] ) : ?>
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
        <?php else : ?>
            <?php // Still submitted, just not editable by the customer. ?>
            <input
                type="hidden"
                class="custom-wvb__qty"
                value="<?php echo esc_attr( $cwvb['quantity'] ); ?>"
            >
        <?php endif; ?>

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