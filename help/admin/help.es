<?php
// IGNORE_THIS_FILE_FOR_TRANSLATION
$tip = '<p><span class="tip">Sugerencia: Si no puede encontrar el texto requerido, puede agregar más textos en la pestaña Propiedades.</span></p>';
$macros = <<<'EOD'
<p>Puede crear cadenas de explicación dinámicas utilizando macros. Cuando usa macros, el motor de plantillas calcula y sustituye valores de cantidad en lugar de macros en la cadena de explicación.
Están disponibles las siguientes macros.</p>
<div class="pp-table fill first-col-nowrap first-col-monospace">
<div class="thead"><span>Macro</span><span>Descripcion</span></div>
<div><span>{CRMIN}</span><span>cálculo cantidad mínima</span></div>
<div><span>{CRMAX}</span><span>cálculo cantidad máxima</span></div>
</div>
EOD;

$help = array();

$help['pp_qty_policy'] = <<<'EOD'
<p>La cantidad de producto puede estar sujeta a diferentes políticas. La política define cómo se comporta el módulo y cómo manipula la cantidad de producto.</p>
<p>Cuando un usuario ingresa la cantidad, la cantidad pedida puede especificar un <em>número de items</em> (piezas, paquetes, etc.) en el carrito de compra.
Cuando el usuario presiona el botón "Agregar al carrito", el módulo agrega una fila al pedido.
Cuando el usuario agrega más artículos del mismo producto, el módulo combina todas las cantidades especificadas en la misma fila. Es un comportamiento habitual de PrestaShop.</p>
<p>La cantidad pedida puede especificar un <em>número de unidades enteras</em> (pero no elementos), por ejemplo, metros.
Cuando el usuario ingresa la cantidad de 45 metros para una cuerda, la tarjeta de compra contendrá un producto (cuerda) con la longitud especificada de 45 metros.
y no 45 elementos de la cuerda como sucedería en la instalación estándar de PrestaShop.
Mientras usa unidades enteras, cada vez que el usuario presiona el botón "Agregar al carrito", el módulo agrega una nueva fila con la cantidad especificada al carrito de compras.
<span class="note">Nota: puede anular este comportamiento eligiendo la opción de cantidades agregadas en "modo de cantidad".</span></p>
<p>La cantidad también puede especificar las <em> unidades fraccionarias </em> (cantidad en valores decimales), como kilogramo o metro.
Este es el comportamiento obligatorio para las tiendas de alimentación o telas. Al usar unidades fraccionarias, cada vez que el usuario presiona el botón "Agregar al carrito",
el módulo agrega una nueva fila con la cantidad especificada al carrito de compras.
<span class="note">Nota: puede anular este comportamiento eligiendo la opción de cantidades agregadas en "modo de cantidad".</span></p>
EOD;

$help['pp_qty_mode'] = <<<'EOD'
<p>El modo de cantidad define si puede medir la cantidad exactamente o solo aproximadamente. Por ejemplo, si el usuario pide 2 kg de peras, el peso real puede ser ligeramente diferente. Puede ignorar la diferencia en la tienda o puede dar al usuario una explicación sobre su política comercial.
<br>Esta propiedad solo tiene sentido cuando la política de cantidad se establece en unidades enteras o fraccionarias, pero no en artículos.</p>
<p>La opcion <em>de cantidades agregadas</em> le permite especificar qué sucede cuando el usuario presiona el botón "Agregar al carrito".
Al usar unidades enteras o fraccionarias, cada vez que el usuario presiona el botón "Agregar al carrito", el módulo agrega una nueva fila con la cantidad especificada al carrito de compras. Cuando elige la opción de cantidades agregadas, el módulo intenta agregar una nueva cantidad a la fila existente con el mismo producto en el carrito de compras.</p>
EOD;

$help['pp_display_mode'] = <<<'EOD'
<p>El modo de visualización define cómo el módulo presenta el precio del producto.</p>
<p>En el modo <em>normal</em>, el módulo muestra el precio del producto según lo definido por el tema. Por lo general, el tema enfatiza el precio del producto para atraer la atención del usuario.</p>
<p>En el modo de <em>visualización de precio invertido</em>, el precio unitario se utiliza como el precio principal que se muestra en la página del producto y reemplaza el precio normal en la página de inicio y en las categorías o páginas de productos de venta cruzada.
Si vende un producto caro, puede especificar las unidades pequeñas con el precio bajo y hacer que su producto se vea más atractivo para el usuario.
<br><span class="note">Nota: debe especificar un texto de precio unitario para el modo de visualización de precio invertido.</span></p>
<p>El modo <em>mostrar el precio minorista como precio unitario</em> indica al módulo que muestre un precio minorista en la posición del precio unitario.
Este modo es útil cuando el producto tiene combinaciones y los precios de combinación son diferentes del precio base.</p>
<p>El modo <em>mostrar el precio unitario base para todas las combinaciones</em> indica al módulo que muestre el precio unitario calculado para el atributo predeterminado para todas las combinaciones.
Este modo es útil cuando el producto tiene combinaciones con diferentes precios y le gustaría mostrar información de precio adicional que no depende del precio de combinación.</p>
<p>El modo <em>mostrar el precio del producto heredado</em> indica al módulo que muestre un precio de producto heredado en lugar de un precio de producto calculado dinámicamente.</p>
<p>El modo<em>mostrar el precio unitario en pedidos y facturas</em> instruye al módulo, para productos con precio unitario mayor que cero, que muestre un precio unitario como un detalle adicional en pedidos y facturas.</p>
<p>El modo <em>mostrar el número de artículos en lugar de la cantidad</em> indica al módulo que muestre una cantidad de artículos pedidos en lugar de una cantidad de producto calculada dinámicamente.
Este modo es útil cuando el producto usa las características multidimensionales y la cantidad total calculada depende del número de artículos y de la cantidad o tamaño de cada artículo.</p>
<p><br>Para brindar más información al usuario, el módulo muestra diferentes textos y detalles adicionales sobre la cantidad, precio del producto,
precio unitario y total en pedidos y facturas. A veces no es aconsejable mostrar esta información. Puede ajustar la visualización de estos detalles seleccionando ocultando detalles adicionales
para <em>precio unitario</em>, <em>cantidad</em> y <em>precio total</em> en pedidos y facturas.</p>
EOD;

$help['pp_price_display_mode'] = <<<'EOD'
<p>La visualización dinámica de precios es un elemento visual adicional agregado en la Extensión de propiedades del producto.
El precio se muestra para la cantidad especificada y se recalcula inmediatamente cuando cambia la cantidad. Esto le da al usuario una mejor experiencia de compra.</p>
<p>En modo <em>normal</em>, el módulo calcula y muestra el precio en un bloque separado.</p>
<p>En el modo <em>como precio del producto</em>, el módulo muestra el precio calculado en lugar del precio del producto predeterminado.</p>
<p>En el modo <em>ocultar visualización de precios</em> el módulo no muestra el precio calculado.</p>
EOD;

$help['pp_unit_price_ratio'] = <<<'EOD'
<p>Relación entre el precio del producto y el precio unitario. Por ejemplo, si vende el producto en kg y le gustaría que el precio unitario se muestre como "por 100 g", especifique la relación del precio unitario como 10.</p>
<p>El módulo utiliza la relación de precio unitario para calcular el precio unitario del producto en la tienda y en el catálogo de productos.
Cuando cambia el precio en el catálogo, el módulo recalcula automáticamente el precio unitario.</p>
EOD;

$help['pp_minimum_price_ratio'] = <<<'EOD'
<p>Un umbral utilizado para calcular el precio mínimo. Cuando el usuario especifica una cantidad menor que el umbral especificado, el precio se calcula utilizando el umbral dado como cantidad. El resultado es que el precio nunca será menor que el precio base multiplicado por el umbral de cantidad dado, independientemente de la cantidad pedida.</p>
EOD;

$help['pp_default_quantity'] = <<<'EOD'
<p>Especifique la cantidad predeterminada para comprar un producto. La cantidad predeterminada es una cantidad inicial que se muestra en la página de un producto.
El módulo también usa la cantidad predeterminada cuando agrega productos a la tarjeta desde las páginas que tienen el botón "Agregar al carrito" pero no ofrecen el campo de cantidad, como las páginas de inicio o categorías de productos.
Establece la cantidad predeterminada en unidades enteras o fraccionarias.</p>
EOD;

$help['pp_qty_step'] = <<<'EOD'
<p>Especifique los incrementos de cantidad para restringir la cantidad ingresada por el usuario a los valores específicos.
El módulo redondea la cantidad pedida al valor que coincide con el paso de cantidad especificado.
Por ejemplo, si vende las telas en incrementos de 0,2 m, puede establecer el paso de cantidad en 0,2.
La cantidad pedida se redondeará a 0,2, 0,4,… 1,6, 1,8, etc.</p>
<p><em>Característica profesional</em><br>En la versión Pro, puede especificar el paso de cantidad en la página del producto en el back office.
Esto funciona tanto para productos simples como para productos con combinaciones. Puede especificar el paso de cantidad por separado para cada combinación.</p>
EOD;

$help['pp_qty_shift'] = <<<'EOD'
<p>Especifique el cambio en la cantidad cuando el usuario presiona los botones hacia arriba y hacia abajo en el campo de cantidad.
Esto se diferencia del paso de cantidad. El cambio de cantidad no restringe la entrada de ningún valor arbitrario.
Este atributo no tiene ningún efecto cuando se utiliza el paso de cantidad o los valores de cantidad específicos.</p>
EOD;

$help['pp_qty_decimals'] = <<<'EOD'
<p>Especifique cuántos decimales desea mostrar para la cantidad. Este atributo solo funciona cuando la política de cantidad se establece en unidades fraccionarias.</p>
EOD;

$help['pp_qty_values'] = <<<'EOD'
<p>Especifique uno o más valores para usar como cantidad de pedido. Al especificar los valores aquí, restringe efectivamente la cantidad ingresada por el usuario a la lista especificada.
Esto puede ser útil, por ejemplo, cuando vende su producto en los paquetes de las cantidades predefinidas.</p>
<p>Si desea especificar solo un valor, simplemente escriba el valor en el campo de entrada de valores de cantidad específicos.<br>
Si desea especificar varios valores, sepárelos con el símbolo de la tubería. Por ejemplo: 10 | 20 | 40 | 100</p>
EOD;

$help['pp_explanation'] = <<<'EOD'
<p>La explicación en línea aparece en el bloque de pedidos en la página de un producto. Por lo general, es un mensaje especial que explica la política comercial, pero puede ser cualquier texto o HTML válido y puede incluir imágenes.</p>
<p>Puede crear cadenas de explicación dinámicas utilizando macros. Cuando usa macros, el motor de plantillas calcula y sustituye valores de cantidad en lugar de macros en la cadena de explicación.</p>
Están disponibles las siguientes macros.</p>
<div class="pp-table fill first-col-nowrap first-col-monospace">
<div class="thead"><span>Macro</span><span>Descripción</span></div>
<div><span>{MIN}</span><span>cantidad mínima</span></div>
<div><span>{MAX}</span><span>cantidad máxima</span></div>
<div><span>{TMAX}</span><span>cantidad total máxima</span></div>
<div><span>{STEP}</span><span>paso de cantidad</span></div>
<div><span>{URATIO}</span><span>relación de precio unitario</span></div>
</div>
EOD;
$help['pp_explanation'] .= '<br>' . $tip;

$help['pp_css'] = <<<'EOD'
<p>Clases CSS separadas por espacio. El módulo agrega estas clases a HTML para productos que usan esta plantilla. Puede utilizar este campo para ajustar el aspecto del producto en la tienda.</p>
<p>Puede crear sus propias clases CSS y agregarlas en el siguiente archivo: <span style="white-space: nowrap; font-family: monospace;">themes/&lt;your_theme_name&gt;/modules/pproperties/css/custom.css</span></p>
<p>También puede utilizar una serie de clases predefinidas.</p>
<div class="pp-table fill first-col-nowrap first-col-monospace">
<div class="thead"><span>Clase CSS</span><span>Descripción</span></div>
<div><span>psm-hide-{cssClass}</span><span>Genere automáticamente el estilo CSS para ocultar cualquier elemento en la pantalla. Reemplazar una cadena de texto <span style="white-space: nowrap; font-family: monospace;">{cssClass}</span> < con un nombre de clase CSS válido para generar una definición de estilo CSS relevante.
<br>Por ejemplo, <span style="white-space: nowrap; font-family: monospace;">psm-hide-product-unit-price</span> generará el siguiente estilo CSS:<br>
<span style="white-space: nowrap; font-family: monospace;">.psm-hide-product-unit-price .product-unit-price {display: none !important;}</span>
</span></span></div>
<div><span>psm-display-{cssClass}</span><span>Genere automáticamente el estilo CSS para mostrar cualquier elemento previamente oculto en la pantalla. Reemplazar una cadena de texto <span style="white-space: nowrap; font-family: monospace;">{cssClass}</span> con un nombre de clase CSS válido para generar una definición de estilo CSS relevante.
<br>>Por ejemplo, <span style="white-space: nowrap; font-family: monospace;">psm-display-product-unit-price</span> generará el siguiente estilo CSS:<br>
<span style="white-space: nowrap; font-family: monospace;">.psm-display-product-unit-price .product-unit-price {display: inherit !important;}</span>
</span></span></div>
<div><span>psm-attribute-label-highlight</span><span>Resalte y enfatice los nombres de los grupos de atributos de productos en una página de productos.</span></div>
<div><span>psm-attribute-color-{size}</span><span>Tamaño de un rectángulo visual para un grupo de atributos de producto definido como un atributo de color.
Reemplazar a <span style="white-space: nowrap; font-family: monospace;">{size}</span> cadena con uno de los siguientes valores: <span style="white-space: nowrap; font-family: monospace;">
pequeño, mediano, grande, xlarge, xxlarge, xxxlarge, jumbo</span>.</span></div>
<div><span>psm-attribute-color-text-visible</span><span>Haga que los nombres de los atributos sean visibles para un grupo de atributos de productos definido como un atributo de color.</span></div>
<div><span>pp-quantity-wanted-hidden</span><span>Oculte un campo de cantidad de entrada en la página del producto.</span></div>
<div><span>pp-product-list-add-to-cart-hidden</span><span>Oculte el botón "agregar al carrito" en la categoría, productos nuevos, productos populares, productos más vendidos, resultados de búsqueda y otras páginas que muestran una lista de productos.
<br><em>Nota: funciona solo con temas compatibles.</em></span></div>
<div><span>pp-packs-calculator-quantity-hidden</span><span>Oculte el campo de cantidad de la calculadora de paquetes en la página de un producto cuando la plantilla utilice la función de cálculo de paquetes de complementos multidimensionales.</span></div>
<div><span>pp-ext-highlight</span><span>Resalte y enfatice el bloque de dimensiones en la página de un producto cuando la plantilla utiliza las funciones de complementos multidimensionales.</span></div>
<div><span>pp-ext-nohighlight</span><span>No resalte ni enfatice el bloque de dimensiones en la página de un producto cuando la plantilla utiliza las funciones de complementos multidimensionales.</span></div>
<div><span>pp-ext-position-after-{cssClass}</span><span>Move the dimensions block after the specified element on the screen. Replace a <span style="white-space: nowrap; font-family: monospace;">{cssClass}</span> string with a valid CSS class name.
<br>For example, <span style="white-space: nowrap; font-family: monospace;">pp-ext-position-after-product-variants</span> will place the dimensions block after the product attributes.
<br><em>Note: the dimensions block can be moved only within the same HTML form element.</em></span></span></div>
</div>
EOD;

$help['pp_ms_display'] = <<<'EOD'
<p><em>Pro feature</em><br><br>Seleccionando <em>visible</em> indica al módulo que agregue un bloque que permita a los clientes elegir el sistema de medida de unidad preferido
en la página del producto y calcule las cantidades y los precios en consecuencia.
<br>Consulte la documentación de la versión Pro para obtener detalles adicionales sobre cómo utilizar esta función.</p>
EOD;

$help['pp_customization'] = <<<'EOD'
<p>El módulo Extensión de propiedades del producto admite personalizaciones definidas por el usuario. Puede escribir su propio código en PHP y el módulo llamará a su código cuando sea necesario.</p>
<p>Puede escribir, por ejemplo, código que valide productos en un pedido utilizando sus propias reglas. Desde su código, puede llamar a los métodos internos de PrestaShop, acceder a la base de datos o realizar cualquier otra actividad diseñada.
Esto brinda infinitas posibilidades para ajustar el módulo a sus necesidades.</p>
<p>Para utilizar las personalizaciones, debe instalar el módulo gratuito "Personalización de la extensión de propiedades del producto". Póngase en contacto con nuestro amable servicio de atención al cliente para obtener instrucciones. El equipo de PS & More también puede escribir código para usted y brinda un servicio de personalización de pago para nuestros clientes.</p>
EOD;

$help['pp_ext_method'] = <<<'EOD'
<div class="pp-table first-col-nowrap">
<div><span>multiplicacion:</span><span>las dimensiones en todas las direcciones se multiplican (dando área o volumen)</span></div>
<div><span>suma:</span><span>se añaden dimensiones en todas las direcciones</span></div>
<div><span>dimensión única:</span><span>la cantidad de dimensión especificada se utiliza para el cálculo del precio</span></div>
<div><span>deshabilitar cálculo:</span><span>las dimensiones sirven solo como campos de entrada y no afectan el cálculo del precio (requiere el complemento Multidimensional Pro o Premium)</span></div>
<div><span>cálculo personalizado:</span><span>cálculo personalizado definido por el usuario (requiere el complemento multidimensional Pro o Premium)<br>
Puede escribir su propio código en PHP y el complemento llamará a su código cuando sea necesario.
Desde su código, puede llamar a los métodos internos de PrestaShop, acceder a la base de datos o realizar cualquier otra actividad diseñada.
Esto brinda infinitas posibilidades para realizar cualquier cálculo que necesite.</span></div>
</div>
EOD;

$help['pp_ext_precision'] = <<<'EOD'
<p>Round the display of the calculation result and the total calculation result to the specified number of decimal digits.
If the precision is positive, the number is rounded to precision significant digits after the decimal point.
If the precision is negative, the number is rounded to precision significant digits before the decimal point.
<br>Round affects only how the number is displayed and not how it is used for the price calculations.</p>
EOD;

$help['pp_ext_explanation'] = <<<'EOD'
<p>Una explicación que se muestra debajo del resultado del cálculo. Por lo general, es un mensaje especial que explica la política comercial, pero puede ser cualquier mensaje.</p>
EOD;
$help['pp_ext_explanation'] .= $macros.'<br>'. $tip;

$help['pp_ext_minimum_quantity_text'] = <<<'EOD'
<p>Texto mostrado cuando la cantidad calculada es mayor que el mínimo especificado.</p>
EOD;
$help['pp_ext_minimum_quantity_text'] .= $macros.'<br>'. $tip;

$help['pp_ext_maximum_quantity_text'] = <<<'EOD'
<p>Texto mostrado cuando la cantidad calculada es mayor que el máximo especificado.</p>
EOD;
$help['pp_ext_maximum_quantity_text'] .= $macros.'<br>'. $tip;

$help['pp_ext_minimum_price_ratio'] = <<<'EOD'
<p>Un umbral utilizado para calcular el precio mínimo. Después de que el usuario especifica cantidades multidimensionales, el cálculo se realiza en función del método de cálculo dado.
Si el precio de la cantidad calculada es menor que el umbral especificado, el precio calculado utilizando el umbral dado como cantidad.
El resultado es que el precio nunca será menor que el precio base multiplicado por el umbral de cantidad dado, independientemente de la cantidad pedida.</p>
EOD;

$help['pp_ext_policy'] = <<<'EOD'
<p>La política de dimensiones especifica cómo se utilizan las dimensiones.</p>
<p>En el modo <em>por defecto</em> de política de dimensiones el cliente ingresa las dimensiones en una página de producto. Por ejemplo, ancho, largo o alto.
En este modo el producto representado por dos cantidades diferentes. El uno es la cantidad calculada utilizando el método de cálculo especificado y puede ser un cuadrado, perímetro, volumen u otra cosa.
La otra es la cantidad que el cliente ingresa en el campo de cantidad regular. Esta cantidad especifica el número de artículos pedidos donde cada artículo tiene el tamaño de la cantidad calculada.</p>
<p>En el modo <em>calculadora de paquetes</em> el cliente especifica las dimensiones para calcular el número de artículos del producto.
Por ejemplo, si vende baldosas en cajas de cartón, las dimensiones utilizadas para calcular el área. El área calculada en metros cuadrados (u otras unidades) determina la cantidad de cajas y el precio.</p>
<p>Para especificar la cantidad del producto en el paquete (la cantidad de metros cuadrados en la caja en nuestro ejemplo) use el campo "relación de precio unitario" en la plantilla
o el campo "precio unitario" en una página de producto en el catálogo de productos en el back office.</p>
EOD;

$help['pp_ext_dimensions'] = <<<'EOD'
<div class="pp-table first-col-nowrap">
<div><span>dimensión:</span><span>Nombre de dimensión.<br><i>Establezca este campo en blanco para eliminar la dimensión.</i></span></div>
<div><span>quantity text:</span><span>Texto mostrado después de la cantidad de la dimensión.<br>
El texto designa el significado de la dimensión. El campo "texto de cantidad" se utiliza en una página de producto en la tienda.
Utilice el campo "texto de cantidad de pedido" para especificar un texto que se mostrará en pedidos y facturas.</span></div>
<div><span>cantidad mínima:</span><span>Cantidad mínima para la dimensión. La cantidad mínima se puede establecer en unidades enteras o fraccionarias.</span></div>
<div><span>cantidad máxima:</span><span>Cantidad máxima para la dimensión. La cantidad máxima se puede establecer en unidades enteras o fraccionarias.</span></div>
<div><span>cantidad por defecto:</span><span>
Cantidad predeterminada para la dimensión. La cantidad predeterminada es una cantidad inicial que se muestra para la dimensión en una página de producto.
La cantidad predeterminada se puede establecer en unidades enteras o fraccionarias.</span></div>
<div><span>quantity step:</span><span>Incrementos de cantidad de dimensión. El complemento redondea la cantidad al valor que coincide con el paso de cantidad especificado.
<br>Por ejemplo, si utiliza la dimensión para la longitud y la longitud debe limitarse a incrementos de 0,2 m, puede establecer el paso de cantidad en 0,2.
El valor ingresado se redondeará a 0.2, 0.4, ... 1.6, 1.8, etc.</span></div>
<div><span>quantity ratio:</span><span>Relación entre la unidad de medida de la dimensión y la unidad de medida del precio.
Al calcular el precio, el módulo multiplica la cantidad de dimensión por la relación especificada y usa el resultado como una cantidad para los cálculos de precio.
<br>Por ejemplo, si establece el precio del producto para m<sup>2</sup> y le gustaría especificar la longitud en cm, especifique la relación como 100.</span></div>
<div><span>texto de cantidad de pedido:</span><span>Texto que se muestra después de la cantidad de la dimensión en pedidos y facturas. Utilice el campo "texto de cantidad" para especificar un texto que se muestra en la página de un producto en la tienda.</span></div>
</div>
EOD;
