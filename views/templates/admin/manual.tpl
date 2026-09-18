{*
 * DWC PrestaShop MCP - in-module manual (Manual tab).
 *
 * Documentación estática dentro de la página de configuración del módulo,
 * organizada como índice a la izquierda + secciones a la derecha. Cada
 * herramienta habilitada añade una nueva sección con ejemplos.
 *
 * Envuelto en {literal} para que las llaves { } del JSON/código no las parsee
 * Smarty. Mantener sincronizado con docs/manual-configuracion-mcp.md.
 *
 * @license MIT
 *}
{literal}
<style>
    .dwc-manual { display: flex; gap: 20px; }
    .dwc-manual .dwc-toc {
        flex: 0 0 240px; position: sticky; top: 10px; align-self: flex-start;
        max-height: calc(100vh - 40px); overflow-y: auto;
        background: #fafafa; border: 1px solid #e0e0e0; border-radius: 4px;
        padding: 14px 10px; font-size: 13px;
    }
    .dwc-manual .dwc-toc h5 {
        margin: 0 0 10px 0; padding-bottom: 6px; font-weight: bold;
        border-bottom: 1px solid #e0e0e0; text-transform: uppercase; font-size: 11px; color: #666;
    }
    .dwc-manual .dwc-toc ol { list-style: none; padding-left: 0; margin: 0; }
    .dwc-manual .dwc-toc ol li { margin: 4px 0; }
    .dwc-manual .dwc-toc ol li a { color: #25b9d7; text-decoration: none; display: block; padding: 4px 6px; border-radius: 3px; }
    .dwc-manual .dwc-toc ol li a:hover { background: #eaf7fa; }
    .dwc-manual .dwc-toc .dwc-toc-sub { padding-left: 14px; font-size: 12px; }
    .dwc-manual .dwc-toc .dwc-toc-sub a { color: #666; }
    .dwc-manual .dwc-toc-badge {
        display: inline-block; font-size: 10px; padding: 1px 5px; border-radius: 3px;
        color: #fff; margin-left: 4px; vertical-align: middle;
    }
    .dwc-manual .dwc-toc-badge.read { background: #5cb85c; }
    .dwc-manual .dwc-toc-badge.write { background: #d9534f; }

    .dwc-manual .dwc-content { flex: 1 1 auto; max-width: 900px; }
    .dwc-manual .dwc-section {
        padding-top: 15px; margin-bottom: 30px;
        border-bottom: 1px dashed #e0e0e0; padding-bottom: 20px;
    }
    .dwc-manual .dwc-section:last-child { border-bottom: none; }
    .dwc-manual h4 { margin-top: 5px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0; }
    .dwc-manual h5 { margin-top: 18px; font-weight: bold; }
    .dwc-manual pre {
        background: #f7f7f9; border: 1px solid #e1e1e8; border-radius: 4px;
        padding: 10px; white-space: pre; overflow-x: auto;
    }
    .dwc-manual code { color: #c7254e; background: #f9f2f4; padding: 1px 4px; border-radius: 3px; }
    .dwc-manual pre code { color: inherit; background: none; padding: 0; }
    .dwc-manual .label-write { background-color: #d9534f; }
    .dwc-manual .label-read { background-color: #5cb85c; }
    .dwc-manual .dwc-example {
        background: #f0f8ff; border-left: 4px solid #25b9d7;
        padding: 10px 14px; margin: 12px 0; border-radius: 0 4px 4px 0;
    }
    .dwc-manual .dwc-example p:last-child { margin-bottom: 0; }
</style>

<div class="dwc-manual">

    <!-- ==================== ÍNDICE ==================== -->
    <nav class="dwc-toc">
        <h5>Índice</h5>
        <ol>
            <li><a href="#dwc-sec-intro">Introducción</a></li>
            <li>
                <a href="#dwc-sec-config">1. Configuración del MCP</a>
                <ol class="dwc-toc-sub">
                    <li><a href="#dwc-sec-config-req">1.1 Requisitos</a></li>
                    <li><a href="#dwc-sec-config-install">1.2 Instalación</a></li>
                    <li><a href="#dwc-sec-config-page">1.3 Página de configuración</a></li>
                    <li><a href="#dwc-sec-config-client">1.4 Conectar un cliente de IA</a></li>
                    <li><a href="#dwc-sec-config-verify">1.5 Verificar que funciona</a></li>
                    <li><a href="#dwc-sec-config-security">1.6 Seguridad</a></li>
                    <li><a href="#dwc-sec-config-troubleshoot">1.7 Resolución de problemas</a></li>
                </ol>
            </li>
            <li>
                <a href="#dwc-sec-tools">2. Qué le puedes pedir al asistente</a>
                <ol class="dwc-toc-sub">
                    <li><a href="#dwc-cat-tienda"><strong>Tienda</strong></a>
                        <ol class="dwc-toc-sub">
                            <li><a href="#dwc-tool-store-info">Ver los datos de la tienda <span class="dwc-toc-badge read">consulta</span></a></li>
                        </ol>
                    </li>
                    <li><a href="#dwc-cat-productos"><strong>Productos</strong></a>
                        <ol class="dwc-toc-sub">
                            <li><a href="#dwc-tool-low-stock">Ver productos con stock bajo <span class="dwc-toc-badge read">consulta</span></a></li>
                            <li><a href="#dwc-tool-search-products">Buscar un producto <span class="dwc-toc-badge read">consulta</span></a></li>
                            <li><a href="#dwc-tool-product-stock">Ver el stock de un producto <span class="dwc-toc-badge read">consulta</span></a></li>
                            <li><a href="#dwc-tool-update-product">Actualizar un producto <span class="dwc-toc-badge write">modifica</span></a></li>
                        </ol>
                    </li>
                    <li><a href="#dwc-cat-pedidos"><strong>Pedidos y ventas</strong></a>
                        <ol class="dwc-toc-sub">
                            <li><a href="#dwc-tool-orders-by-status">Ver pedidos por estado <span class="dwc-toc-badge read">consulta</span></a></li>
                            <li><a href="#dwc-tool-sales-range">Ver ventas por fechas <span class="dwc-toc-badge read">consulta</span></a></li>
                            <li><a href="#dwc-tool-top-selling">Ver los más vendidos <span class="dwc-toc-badge read">consulta</span></a></li>
                            <li><a href="#dwc-tool-abandoned-carts">Ver carritos abandonados <span class="dwc-toc-badge read">consulta</span></a></li>
                        </ol>
                    </li>
                    <li><a href="#dwc-cat-clientes"><strong>Clientes</strong></a>
                        <ol class="dwc-toc-sub">
                            <li><a href="#dwc-tool-customers">Buscar o listar clientes <span class="dwc-toc-badge read">consulta</span></a></li>
                        </ol>
                    </li>
                </ol>
            </li>
            <!-- Nuevas secciones se añaden aquí conforme se habiliten funcionalidades. -->
        </ol>
    </nav>

    <!-- ==================== CONTENIDO ==================== -->
    <div class="dwc-content">

        <!-- ---------- Introducción ---------- -->
        <section class="dwc-section" id="dwc-sec-intro">
            <h4>Introducción</h4>
            <p class="text-muted">
                Servidor MCP autónomo dentro de PrestaShop. Expone la tienda a un cliente
                de IA (Claude, ChatGPT, Gemini, MCP Inspector) mediante su propio endpoint
                HTTP autenticado por token. No depende de <code>ps_mcp_server</code>.
            </p>
            <p><strong>Módulo:</strong> dwcprestamcp &middot; <strong>Versión:</strong> 2.3.0 &middot; <strong>Licencia:</strong> MIT</p>
            <p>
                Este manual está pensado para crecer: primero la configuración, después una
                sección por cada herramienta con <strong>ejemplos de uso</strong>. Cuando
                habilitemos una nueva función, se añadirá aquí con su ejemplo.
            </p>
        </section>

        <!-- ==================== 1. CONFIGURACIÓN ==================== -->
        <section class="dwc-section" id="dwc-sec-config">
            <h4>1. Configuración del MCP</h4>

            <!-- 1.1 -->
            <div id="dwc-sec-config-req">
                <h5>1.1 Requisitos</h5>
                <table class="table table-bordered">
                    <tbody>
                        <tr><td><strong>PrestaShop</strong></td><td>8.2+ o 9.x</td></tr>
                        <tr><td><strong>PHP</strong></td><td>8.1+</td></tr>
                        <tr><td><strong>Composer</strong></td><td>Para instalar el SDK de MCP en <code>vendor/</code> (o usar un ZIP que ya lo incluya)</td></tr>
                        <tr><td><strong>HTTPS</strong></td><td>Obligatorio en producción (el token viaja en cada petición)</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- 1.2 -->
            <div id="dwc-sec-config-install">
                <h5>1.2 Instalación</h5>
                <p><strong>a) Colocar el módulo y sus dependencias.</strong> Desde la raíz de la tienda:</p>
                <pre><code>git clone https://github.com/ximo13/dwcprestamcp.git modules/dwcprestamcp
cd modules/dwcprestamcp
composer install --no-dev</code></pre>
                <div class="alert alert-info">
                    <strong>ZIP para comercios (sin Composer):</strong> si prefieres instalar por
                    <em>Módulos &rarr; Subir un módulo</em>, usa un paquete de release que ya incluya
                    la carpeta <code>vendor/</code>. Sin <code>vendor/autoload.php</code> el endpoint
                    devuelve un error 500 pidiendo ejecutar <code>composer install</code>.
                </div>
                <p><strong>b) Instalar el módulo en PrestaShop.</strong></p>
                <ul>
                    <li><strong>Back office:</strong> <em>Módulos &rarr; Gestor de módulos</em>, busca "DWC PrestaShop MCP" e instálalo.</li>
                    <li><strong>CLI:</strong> <code>php bin/console prestashop:module install dwcprestamcp</code></li>
                </ul>
                <p>
                    Al instalarse, el módulo <strong>genera automáticamente un token de acceso</strong>
                    aleatorio (32 bytes &rarr; 64 caracteres hex) y lo guarda en la clave de
                    configuración <code>DWCPRESTAMCP_TOKEN</code>.
                </p>
            </div>

            <!-- 1.3 -->
            <div id="dwc-sec-config-page">
                <h5>1.3 Página de configuración</h5>
                <p>En la pestaña <strong>Configuración</strong> tienes tres campos de solo lectura, listos para copiar:</p>
                <ol>
                    <li><strong>MCP endpoint URL</strong> &mdash; la URL que debes dar al cliente de IA: <code>https://TU-TIENDA.tld/modules/dwcprestamcp/mcp.php</code></li>
                    <li><strong>Access token (Bearer)</strong> &mdash; el token secreto. <strong>No lo compartas.</strong></li>
                    <li><strong>Client configuration (example)</strong> &mdash; JSON listo para pegar en tu cliente MCP.</li>
                </ol>
                <p>
                    El botón <strong>Regenerar token</strong> crea uno nuevo y el anterior
                    deja de funcionar de inmediato: úsalo si el token se ha filtrado o de forma
                    periódica por higiene de seguridad.
                </p>
            </div>

            <!-- 1.4 -->
            <div id="dwc-sec-config-client">
                <h5>1.4 Conectar un cliente de IA</h5>
                <p><strong>Remoto (HTTP) &mdash; Claude, ChatGPT, etc.</strong></p>
                <pre><code>{
    "mcpServers": {
        "prestashop-dwc": {
            "url": "https://TU-TIENDA.tld/modules/dwcprestamcp/mcp.php",
            "headers": {
                "Authorization": "Bearer TU_TOKEN"
            }
        }
    }
}</code></pre>
                <p><strong>Local (STDIO) &mdash; Claude Desktop en la misma máquina.</strong></p>
                <pre><code>{
    "mcpServers": {
        "prestashop-dwc-local": {
            "command": "php",
            "args": ["/ruta/absoluta/modules/dwcprestamcp/bin/mcp-stdio.php"]
        }
    }
}</code></pre>
            </div>

            <!-- 1.5 -->
            <div id="dwc-sec-config-verify">
                <h5>1.5 Verificar que funciona</h5>
                <ol>
                    <li>Guarda la configuración en tu cliente MCP.</li>
                    <li>Reinicia/reconecta el cliente para que descubra las herramientas.</li>
                    <li>Ejecuta <code>dwc_get_store_info</code>. Debe devolver algo como:</li>
                </ol>
                <pre><code>{
    "shop_name": "My Shop",
    "prestashop_version": "9.x.x",
    "php_version": "8.x.x",
    "default_language": "xx",
    "default_currency": "XXX"
}</code></pre>
            </div>

            <!-- 1.6 -->
            <div id="dwc-sec-config-security">
                <h5>1.6 Seguridad</h5>
                <ul>
                    <li><strong>Token Bearer</strong> comparado con <code>hash_equals</code>. Sin token válido &rarr; <strong>401 Unauthorized</strong>.</li>
                    <li><strong>HTTPS siempre en producción.</strong></li>
                    <li><strong>Anti DNS-rebinding:</strong> lista blanca de hosts (<code>localhost</code>, <code>127.0.0.1</code>, <code>[::1]</code>, <code>PS_SHOP_DOMAIN</code>, <code>PS_SHOP_DOMAIN_SSL</code>).</li>
                    <li><strong>CORS</strong> mediante middleware.</li>
                    <li><strong>.htaccess propio:</strong> bloquea todos los <code>.php</code> del módulo salvo <code>mcp.php</code>, y lo exime del WAF (LiteSpeed/OVH).</li>
                </ul>
            </div>

            <!-- 1.7 -->
            <div id="dwc-sec-config-troubleshoot">
                <h5>1.7 Resolución de problemas</h5>
                <table class="table table-bordered">
                    <thead><tr><th>Síntoma</th><th>Causa</th><th>Solución</th></tr></thead>
                    <tbody>
                        <tr><td>401 Unauthorized</td><td>Token mal copiado o regenerado</td><td>Copia de nuevo el token; revisa <code>Authorization: Bearer ...</code></td></tr>
                        <tr><td>500 "MCP dependencies are not installed"</td><td>Falta <code>vendor/autoload.php</code></td><td><code>composer install --no-dev</code> en el módulo</td></tr>
                        <tr><td>El WAF bloquea el endpoint</td><td>Regla ModSecurity del hosting</td><td>Usa el endpoint físico <code>.../mcp.php</code></td></tr>
                        <tr><td>Host rechazado</td><td>Dominio no listado</td><td>Añádelo en <em>Parámetros de la tienda &rarr; Tráfico y SEO</em></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ==================== 2. QUÉ LE PUEDES PEDIR ==================== -->
        <section class="dwc-section" id="dwc-sec-tools">
            <h4>2. Qué le puedes pedir al asistente</h4>
            <p>
                Estas son las acciones que el asistente puede hacer en tu tienda. Solo
                tienes que <strong>escribírselo en lenguaje normal</strong>: no hace falta
                indicar nombres técnicos ni códigos. Junto a cada acción tienes ejemplos
                de frases que puedes copiar tal cual.
            </p>
            <p>
                <span class="label label-read">consulta</span> solo lee datos, no cambia nada. &nbsp;
                <span class="label label-write">modifica</span> cambia datos reales de la tienda.
            </p>

            <!-- ==================== Categoría: Tienda ==================== -->
            <h5 id="dwc-cat-tienda" style="margin-top: 25px; color: #25b9d7; text-transform: uppercase; letter-spacing: 1px; font-size: 12px;">
                <i class="icon icon-home"></i> Tienda
            </h5>

            <!-- ---------- Ver datos de la tienda ---------- -->
            <div id="dwc-tool-store-info" style="padding-top: 10px;">
                <h5>Ver los datos de la tienda &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Muestra el nombre de la tienda, la versión de PrestaShop, el idioma y la moneda por defecto. Útil para comprobar rápidamente que el asistente está conectado a tu tienda.</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;Dime los datos de mi tienda.&rdquo;</p>
                    <p>&ldquo;¿Qué versión de PrestaShop tengo?&rdquo;</p>
                    <p>&ldquo;¿En qué idioma y moneda está configurada la tienda?&rdquo;</p>
                </div>
            </div>

            <!-- ==================== Categoría: Productos ==================== -->
            <h5 id="dwc-cat-productos" style="margin-top: 35px; color: #25b9d7; text-transform: uppercase; letter-spacing: 1px; font-size: 12px;">
                <i class="icon icon-cube"></i> Productos
            </h5>

            <!-- ---------- Ver productos con stock bajo ---------- -->
            <div id="dwc-tool-low-stock" style="padding-top: 10px;">
                <h5>Ver productos con stock bajo &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Lista los productos que están por debajo de un umbral de existencias. Útil para saber qué tienes que reponer. Puedes decirle el umbral (por defecto, 5 unidades) y cuántos productos quieres ver (por defecto, 50).</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;¿Qué productos tengo con stock bajo?&rdquo;</p>
                    <p>&ldquo;Dame los productos con menos de 3 unidades.&rdquo;</p>
                    <p>&ldquo;Enséñame los 20 productos con menos stock.&rdquo;</p>
                    <p>&ldquo;¿Qué tengo agotado?&rdquo; <span class="text-muted">(umbral 0)</span></p>
                </div>
            </div>

            <!-- ---------- Buscar un producto ---------- -->
            <div id="dwc-tool-search-products" style="padding-top: 25px;">
                <h5>Buscar un producto &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Busca productos por <strong>nombre</strong> o <strong>referencia</strong> y muestra su precio, stock y si está publicado. Útil para encontrar el ID de un producto antes de actualizarlo.</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;Busca el producto &lsquo;musk&rsquo;.&rdquo;</p>
                    <p>&ldquo;¿Qué productos tengo con la palabra &lsquo;vela&rsquo;?&rdquo;</p>
                    <p>&ldquo;Busca la referencia D-244165.&rdquo;</p>
                </div>
            </div>

            <!-- ---------- Ver el stock de un producto ---------- -->
            <div id="dwc-tool-product-stock" style="padding-top: 25px;">
                <h5>Ver el stock de un producto &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Muestra las existencias de un producto. Si tiene <strong>combinaciones</strong> (tallas, colores…), desglosa el stock de cada una. Necesita el ID del producto.</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;¿Cuánto stock tiene el producto 123?&rdquo;</p>
                    <p>&ldquo;Dame el stock por tallas del producto 123.&rdquo;</p>
                </div>
            </div>

            <!-- ---------- Actualizar un producto ---------- -->
            <div id="dwc-tool-update-product" style="padding-top: 25px;">
                <h5>Actualizar un producto &nbsp;<span class="label label-write">modifica</span></h5>
                <p>
                    Cambia datos de un producto existente. Puedes cambiar el <strong>precio</strong>,
                    el <strong>peso</strong>, el <strong>nombre</strong>, la <strong>referencia</strong>,
                    el <strong>stock</strong>, <strong>ocultarlo o publicarlo</strong> del catálogo,
                    y marcar la <strong>etiqueta de oferta</strong>. Solo cambia lo que le pidas;
                    el resto queda como estaba.
                </p>
                <p>
                    Necesita saber <strong>de qué producto hablas</strong>, así que dale su ID
                    (el número que ves en el listado del back office).
                </p>

                <div class="alert alert-warning">
                    <strong>Ojo con "poner en oferta":</strong> hoy por hoy esta acción solo enciende
                    la <em>etiqueta visual</em> "¡En oferta!" en la ficha del producto. <strong>No
                    baja el precio ni configura ningún descuento.</strong> Para una rebaja real
                    (que el precio baje de verdad) hay que crear un <em>precio específico</em>
                    desde el back office, o esperar a que habilitemos una acción específica.
                </div>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p><strong>Cambiar stock</strong></p>
                    <p>&ldquo;Pon 20 unidades de stock al producto 123.&rdquo;</p>
                </div>
                <div class="dwc-example">
                    <p><strong>Cambiar precio y peso</strong></p>
                    <p>&ldquo;Actualiza el producto 123: precio 15,90 y peso 1,5.&rdquo;</p>
                </div>
                <div class="dwc-example">
                    <p><strong>Cambiar el nombre o la referencia</strong></p>
                    <p>&ldquo;Cambia el nombre del producto 123 a &lsquo;Vela aromática lavanda&rsquo;.&rdquo;</p>
                    <p>&ldquo;Ponle al producto 123 la referencia VEL-LAV-01.&rdquo;</p>
                </div>
                <div class="dwc-example">
                    <p><strong>Ocultar o publicar en la web</strong></p>
                    <p>&ldquo;Oculta el producto 123 del catálogo.&rdquo;</p>
                    <p>&ldquo;Publica el producto 123.&rdquo;</p>
                </div>
                <div class="dwc-example">
                    <p><strong>Marcar la etiqueta de oferta</strong> <span class="text-muted">(solo la etiqueta, no baja el precio)</span></p>
                    <p>&ldquo;Marca el producto 123 con la etiqueta de en oferta.&rdquo;</p>
                    <p>&ldquo;Quita la etiqueta de oferta al producto 123.&rdquo;</p>
                </div>

                <p><strong>Buenas prácticas:</strong></p>
                <ul>
                    <li>Cuando haga un cambio, el asistente te dirá exactamente qué modificó. Revísalo antes de dar por hecho el cambio.</li>
                    <li>Si te equivocas al pedirlo, dile <em>&ldquo;deshaz&rdquo;</em> con el valor anterior y vuelve a lanzarlo (por ejemplo, <em>&ldquo;vuelve a poner el precio del producto 123 en 12,50&rdquo;</em>).</li>
                    <li>El asistente no puede borrar productos ni tocar los pedidos: solo modifica los campos indicados arriba.</li>
                </ul>
            </div>

            <!-- ==================== Categoría: Pedidos y ventas ==================== -->
            <h5 id="dwc-cat-pedidos" style="margin-top: 35px; color: #25b9d7; text-transform: uppercase; letter-spacing: 1px; font-size: 12px;">
                <i class="icon icon-shopping-cart"></i> Pedidos y ventas
            </h5>

            <!-- ---------- Ver pedidos por estado ---------- -->
            <div id="dwc-tool-orders-by-status" style="padding-top: 10px;">
                <h5>Ver pedidos por estado &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Lista los pedidos recientes. Puedes filtrar por su <strong>estado</strong> (pendiente de pago, enviado, entregado…) para ver solo los que te interesan. Muestra referencia, cliente, total y estado.</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;¿Qué pedidos tengo pendientes de pago?&rdquo;</p>
                    <p>&ldquo;Enséñame los últimos pedidos enviados.&rdquo;</p>
                    <p>&ldquo;Dame los 10 pedidos más recientes.&rdquo;</p>
                </div>
            </div>

            <!-- ---------- Ver ventas por fechas ---------- -->
            <div id="dwc-tool-sales-range" style="padding-top: 25px;">
                <h5>Ver ventas por fechas &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Resumen de ventas de un periodo: <strong>total facturado</strong>, <strong>número de pedidos</strong> y <strong>ticket medio</strong>. Por defecto cuenta solo pedidos válidos (pagados).</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;¿Cuánto he facturado del 1 al 30 de septiembre?&rdquo;</p>
                    <p>&ldquo;Ventas de este mes.&rdquo;</p>
                    <p>&ldquo;¿Cuál fue mi ticket medio la semana pasada?&rdquo;</p>
                </div>
            </div>

            <!-- ---------- Ver los más vendidos ---------- -->
            <div id="dwc-tool-top-selling" style="padding-top: 25px;">
                <h5>Ver los productos más vendidos &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Lista los productos que más se han vendido (por unidades) en un periodo, con la cantidad vendida y los ingresos de cada uno. Útil para saber qué funciona mejor.</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;¿Cuáles son mis 5 productos más vendidos?&rdquo;</p>
                    <p>&ldquo;Top 10 de ventas de septiembre.&rdquo;</p>
                    <p>&ldquo;¿Qué se ha vendido más este mes?&rdquo;</p>
                </div>
            </div>

            <!-- ---------- Ver carritos abandonados ---------- -->
            <div id="dwc-tool-abandoned-carts" style="padding-top: 25px;">
                <h5>Ver carritos abandonados &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Muestra los carritos que tienen productos pero <strong>no llegaron a convertirse en pedido</strong>, dentro de los últimos días que indiques (por defecto, 7). Incluye el cliente y cuántos artículos dejó.</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;¿Tengo carritos abandonados esta semana?&rdquo;</p>
                    <p>&ldquo;Carritos abandonados de los últimos 30 días.&rdquo;</p>
                </div>
            </div>

            <!-- ==================== Categoría: Clientes ==================== -->
            <h5 id="dwc-cat-clientes" style="margin-top: 35px; color: #25b9d7; text-transform: uppercase; letter-spacing: 1px; font-size: 12px;">
                <i class="icon icon-user"></i> Clientes
            </h5>

            <!-- ---------- Buscar o listar clientes ---------- -->
            <div id="dwc-tool-customers" style="padding-top: 10px;">
                <h5>Buscar o listar clientes &nbsp;<span class="label label-read">consulta</span></h5>
                <p>Busca clientes por <strong>email</strong>, o lista los <strong>registrados más recientemente</strong>. Muestra nombre, email, fecha de registro y si están activos.</p>

                <p><strong>Ejemplos de frases:</strong></p>
                <div class="dwc-example">
                    <p>&ldquo;Busca al cliente con email juan@ejemplo.com.&rdquo;</p>
                    <p>&ldquo;¿Quiénes son mis últimos clientes registrados?&rdquo;</p>
                    <p>&ldquo;Dame los 20 clientes más recientes.&rdquo;</p>
                </div>
            </div>

            <!-- Aquí se añadirán nuevas acciones conforme se habiliten. -->
        </section>

    </div>
</div>
{/literal}
