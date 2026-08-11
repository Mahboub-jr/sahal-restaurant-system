<?php
/**
 * Shared scripts for pages that still use the legacy Vali layout.
 *
 * WHAT WAS HERE BEFORE, AND WHY IT WAS REMOVED
 * --------------------------------------------
 * This file used to end with hardcoded ECharts demo data followed by:
 *
 *     const salesChartElement = document.getElementById('salesChart');
 *     const salesChart = echarts.init(salesChartElement, ...);
 *
 * #salesChart and #supportRequestChart only ever existed on Vali's demo
 * dashboard. On every real page those lookups returned null, echarts.init(null)
 * threw, and because this block runs after js/main.js the uncaught error
 * stopped all subsequent JavaScript — which is why the sidebar treeview
 * toggles silently stopped working site-wide. See AUDIT.md C2.
 *
 * It also carried the template author's Google Analytics tag, reporting to
 * a third party's property.
 *
 * Pages that genuinely need a chart should load it themselves, guarded by an
 * element check. Converted pages use includes/layout/ instead of this file.
 */
?>
<!-- Core scripts -->
<script src="<?= defined('BASE_URL') ? BASE_URL : '' ?>js/jquery-3.7.0.min.js"></script>
<script src="<?= defined('BASE_URL') ? BASE_URL : '' ?>js/bootstrap.min.js"></script>
<script src="<?= defined('BASE_URL') ? BASE_URL : '' ?>js/main.js"></script>
