<?php

class Pref_System extends Handler_Protected {

	private $log_page_limit = 15;

	function before($method) {
		if (parent::before($method)) {
			if ($_SESSION["access_level"] < 10) {
				print __("Your access level is insufficient to open this tab.");
				return false;
			}
			return true;
		}
		return false;
	}

	function csrf_ignore($method) {
		$csrf_ignored = array("index");

		return array_search($method, $csrf_ignored) !== false;
	}

	function clearLog() {
		$this->pdo->query("DELETE FROM ttrss_error_log");
	}

	function getphpinfo() {
		ob_start();
		phpinfo();
		$info = ob_get_contents();
		ob_end_clean();

		print preg_replace( '%^.*<body>(.*)</body>.*$%ms','$1', (string)$info);
	}

	private function log_viewer(int $page, int $severity) {
		$errno_values = [];

		switch ($severity) {
			case E_USER_ERROR:
				$errno_values = [ E_ERROR, E_USER_ERROR, E_PARSE ];
				break;
			case E_USER_WARNING:
				$errno_values = [ E_ERROR, E_USER_ERROR, E_PARSE, E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED ];
				break;
		}

		if (count($errno_values) > 0) {
			$errno_qmarks = arr_qmarks($errno_values);
			$errno_filter_qpart = "errno IN ($errno_qmarks)";
		} else {
			$errno_filter_qpart = "true";
		}

		$limit = $this->log_page_limit;
		$offset = $limit * $page;

		$sth = $this->pdo->prepare("SELECT
				COUNT(id) AS total_pages
			FROM
				ttrss_error_log
			WHERE
				$errno_filter_qpart");

		$sth->execute($errno_values);

		if ($res = $sth->fetch()) {
			$total_pages = (int)($res["total_pages"] / $limit);
		} else {
			$total_pages = 0;
		}

		?>
		<div dojoType='dijit.layout.BorderContainer' gutters='false'>
			<div region='top' dojoType='fox.Toolbar'>

				<button dojoType='dijit.form.Button' onclick='Helpers.EventLog.refresh()'>
					<?php echo __('Refresh') ?>
				</button>

				<button dojoType='dijit.form.Button' <?php echo ($page <= 0 ? "disabled" : "") ?>
					onclick='Helpers.EventLog.prevPage()'>
					<?php echo __('&lt;&lt;') ?>
				</button>

				<button dojoType='dijit.form.Button' disabled>
					<?php echo T_sprintf('Page %d of %d', $page+1, $total_pages+1) ?>
				</button>

				<button dojoType='dijit.form.Button' <?php echo ($page >= $total_pages ? "disabled" : "") ?>
					onclick='Helpers.EventLog.nextPage()'>
					<?php echo __('&gt;&gt;') ?>
				</button>

				<button dojoType='dijit.form.Button'
					onclick='Helpers.EventLog.clear()'>
					<?php echo __('Clear') ?>
				</button>

				<div class='pull-right'>
					<?php echo __("Severity:") ?>

					<?php print_select_hash("severity", $severity,
						[
							E_USER_ERROR => __("Errors"),
							E_USER_WARNING => __("Warnings"),
							E_USER_NOTICE => __("Everything")
						], 'dojoType="fox.form.Select" onchange="Helpers.EventLog.refresh()"') ?>
				</div>
			</div>

			<div style="padding : 0px" dojoType="dijit.layout.ContentPane" region="center">

				<table width='100%' class='event-log'>

					<tr class='title'>
						<td width='5%'><?php echo __("Error") ?></td>
						<td><?php echo __("Filename") ?></td>
						<td><?php echo __("Message") ?></td>
						<td width='5%'><?php echo __("User") ?></td>
						<td width='5%'><?php echo __("Date") ?></td>
					</tr>

					<?php
					$sth = $this->pdo->prepare("SELECT
							errno, errstr, filename, lineno, created_at, login, context
						FROM
							ttrss_error_log LEFT JOIN ttrss_users ON (owner_uid = ttrss_users.id)
						WHERE
							$errno_filter_qpart
						ORDER BY
							ttrss_error_log.id DESC
						LIMIT $limit OFFSET $offset");

					$sth->execute($errno_values);

					while ($line = $sth->fetch()) {
						foreach ($line as $k => $v) { $line[$k] = htmlspecialchars($v); }
						?>
						<tr>
							<td class='errno'>
								<?php echo Logger::$errornames[$line["errno"]] . " (" . $line["errno"] . ")" ?>
							</td>
							<td class='filename'><?php echo  $line["filename"] . ":" . $line["lineno"] ?></td>
							<td class='errstr'><?php echo  $line["errstr"] . "\n" .  $line["context"] ?></td>
							<td class='login'><?php echo  $line["login"] ?></td>
							<td class='timestamp'>
								<?php echo TimeHelper::make_local_datetime($line["created_at"], false) ?>
							</td>
						</tr>
					<?php } ?>
				</table>
			</div>
		</div>
		<?php
	}

	function index() {

		$severity = (int) ($_REQUEST["severity"] ?? E_USER_WARNING);
		$page = (int) ($_REQUEST["page"] ?? 0);
		?>
		<div dojoType='dijit.layout.AccordionContainer' region='center'>
			<div dojoType='dijit.layout.AccordionPane' style='padding : 0' title='<i class="material-icons">report</i> <?php echo __('Event Log') ?>'>
				<?php
					if (LOG_DESTINATION == "sql") {
						$this->log_viewer($page, $severity);
					} else {
						print_notice("Please set LOG_DESTINATION to 'sql' in config.php to enable database logging.");
					}
				?>
			</div>

			<div dojoType='dijit.layout.AccordionPane' title='<i class="material-icons">info</i> <?php echo __('PHP Information') ?>'>
				<script type='dojo/method' event='onSelected' args='evt'>
					Helpers.System.getPHPInfo(this);
				</script>
				<div class='phpinfo'><?php echo __("Loading, please wait...") ?></div>
			</div>

			<?php PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB, "prefSystem") ?>
		</div>
		<?php
	}
}
