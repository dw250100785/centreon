<?php
/*
 * MERETHIS
 *
 * Source Copyright 2005-2010 MERETHIS
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@merethis.com
 *
 */

class procedures_Proxy  {
	var $DB;
	var $hflag;
	var $sflag;
	var $proc;
	var $url;
	var $wikiUrl;

	public function procedures_Proxy($pearDB, $db_prefix, $host_name, $service_description = NULL) {
		global $etc_centreon, $db_name, $db_user, $db_host, $db_password, $WikiURL;

		$this->DB = $pearDB;
		$this->hflag = 0;
		$this->sflag = 0;

        $centreon_path = realpath(dirname(__FILE__) . '/../../../');
        require_once $centreon_path."/config/wiki.conf.php";
		require_once "$etc_centreon/centreon.conf.php";

		$this->wikiURL = $WikiURL;
		$this->proc = new procedures(3, $db_name, $db_user, $db_host, $db_password, $this->DB, $db_prefix);

		if (isset($host_name)) {
			if (isset($service_description))
				$this->returnServiceWikiUrl($this->DB->escape($host_name), $this->DB->escape($service_description));
			else
				$this->returnHostWikiUrl($this->DB->escape($host_name));
		}
		return;
	}

	private function returnHostWikiUrl($host_name) {
		$this->proc->setHostInformations();

		$procList = $this->proc->getProcedures();

		/*
		 * Check if host has a procedure directly on Host
		 */
		if (isset($procList["Host:".$host_name])) {
			$this->url = $this->wikiURL."/index.php?title=Host:".$host_name;
			return ;
		}

		/*
		 * Check if host can get a procedure on templates
		 */
		$templates = $this->getHostTemplateList($host_name);
		foreach ($templates as $tpl) {
			if (isset($procList["Host-Template:".$tpl])) {
				$this->url = $this->wikiURL."/index.php?title=Host-Template:".$tpl;
				return ;
			}
		}
	}

	private function returnServiceWikiUrl($host_name, $service_description) {
		if ($this->hflag != 0)
			$this->proc->setHostInformations();
		$this->proc->setServiceInformations();
		$this->sflag;

		$procList = $this->proc->getProcedures();

		/*
		 * Check Service
		 */
		if (isset($procList["Service:".trim($host_name."_".$service_description)])) {
			$this->url = $this->wikiURL."/index.php?title=Service:".$host_name."_".$service_description;
			return;
		}

		/*
		 * Check service Template
		 */
		$host_id = $this->getMyHostID($host_name);
		$templates = $this->getMyServiceTemplateModels($this->getMyServicesID($host_id, $service_description));
		foreach ($templates as $key => $value) {
			if (isset($procList["Service-Template:".trim($value)])) {
				$this->url = $this->wikiURL."/index.php?title=Service-Template:".$value;
				return ;
			}
		}
		$this->returnHostWikiUrl($host_name);
	}

	function getMyHostID($host_name = NULL)	{
		$DBRESULT =& $this->DB->query("SELECT host_id FROM host WHERE host_name = '".$host_name."' LIMIT 1");
		$row =& $DBRESULT->fetchRow();
		if ($row["host_id"])
			return $row["host_id"];
	}

	function getMyServicesID($host_id, $service_description) {
		/*
		 * Get Services attached to hosts
		 */
		$DBRESULT =& $this->DB->query("SELECT service_id, service_description FROM service, host_service_relation hsr WHERE hsr.host_host_id = '".$host_id."' AND hsr.service_service_id = service_id AND service_description = '$service_description'");
		while ($elem =& $DBRESULT->fetchRow())	{
			return $elem["service_id"];
		}
		$DBRESULT->free();

		/*
		 * Get Services attached to hostgroups
		 */
		$DBRESULT =& $this->DB->query("SELECT service_id, service_description FROM hostgroup_relation hgr, service, host_service_relation hsr" .
				" WHERE hgr.host_host_id = '".$host_id."' AND hsr.hostgroup_hg_id = hgr.hostgroup_hg_id" .
				" AND service_id = hsr.service_service_id " .
				" AND service_description = '$service_description'");
		while ($elem =& $DBRESULT->fetchRow()){
			return $elem["service_id"];
		}
		$DBRESULT->free();
	}


	private function getHostTemplateList($host_name) {
		$templates = array();

		$DBRESULT =& $this->DB->query("SELECT host_tpl_id FROM `host_template_relation`, `host` WHERE host_host_id = host_id AND host_name = '".$host_name."' ORDER BY `order`");
		while($row =& $DBRESULT->fetchRow())	{
			$DBRESULT2 =& $this->DB->query("SELECT host_name FROM host WHERE host_id = '".$row['host_tpl_id']."' LIMIT 1");
			$hTpl =& $DBRESULT2->fetchRow();
			$templates[$row['host_tpl_id']] = html_entity_decode($hTpl["host_name"], ENT_QUOTES);
		}
		return $templates;
	}

	private function getMyServiceTemplateModels($service_id)	{

		$tplArr = array();

		$DBRESULT =& $this->DB->query("SELECT service_description, service_template_model_stm_id FROM service WHERE service_id = '".$service_id."' LIMIT 1");
		$row =& $DBRESULT->fetchRow();
		$DBRESULT->free();
		$service_id = $row["service_template_model_stm_id"];
		if ($row["service_description"])
			$tplArr[$service_id] = $row["service_description"];
		while (1) {
			$DBRESULT =& $this->DB->query("SELECT service_description, service_template_model_stm_id FROM service WHERE service_id = '".$service_id."' LIMIT 1");
			$row =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			if ($row["service_description"])
				$tplArr[$service_id] = $row["service_description"];
			else
				break;
			if ($row["service_template_model_stm_id"])
				$service_id = $row["service_template_model_stm_id"];
			else
				break;
		}
		return ($tplArr);
	}
}

?>