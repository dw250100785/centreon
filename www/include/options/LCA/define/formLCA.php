<?
/**
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/gpl.txt
Developped by : Julien Mathis - Romain Le Merlus

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/
	
	if (!isset($oreon))
		exit();

	#
	## Database retrieve information for LCA
	#
	if ($o == "c" || $o == "w")	{
		$DBRESULT =& $pearDB->query("SELECT * FROM lca_define WHERE lca_id = '".$lca_id."' LIMIT 1");
		# Set base value
		$lca = array_map("myDecode", $DBRESULT->fetchRow());
		# Set Service Groups relations
		$DBRESULT =& $pearDB->query("SELECT DISTINCT servicegroup_sg_id FROM lca_define_servicegroup_relation WHERE lca_define_lca_id = '".$lca_id."'");
		for($i = 0; $DBRESULT->fetchInto($sg); $i++)
			$lca["lca_sgs"][$i] = $sg["servicegroup_sg_id"];
		$DBRESULT->free();
		# Set Host Groups relations
		$DBRESULT =& $pearDB->query("SELECT DISTINCT hostgroup_hg_id FROM lca_define_hostgroup_relation WHERE lca_define_lca_id = '".$lca_id."'");
		for($i = 0; $DBRESULT->fetchInto($hg); $i++)
			$lca["lca_hgs"][$i] = $hg["hostgroup_hg_id"];
		$DBRESULT->free();
		# Set Host relations
		$DBRESULT =& $pearDB->query("SELECT DISTINCT host_host_id FROM lca_define_host_relation WHERE lca_define_lca_id = '".$lca_id."'");
		for($i = 0; $DBRESULT->fetchInto($host); $i++)
			$lca["lca_hosts"][$i] = $host["host_host_id"];
		$DBRESULT->free();
		# Set Contact Groups relations
		$DBRESULT =& $pearDB->query("SELECT DISTINCT contactgroup_cg_id FROM lca_define_contactgroup_relation WHERE lca_define_lca_id = '".$lca_id."'");
		for($i = 0; $DBRESULT->fetchInto($cg); $i++)
			$lca["lca_cgs"][$i] = $cg["contactgroup_cg_id"];
		$DBRESULT->free();
		# Set Topology relations
		$DBRESULT =& $pearDB->query("SELECT topology_topology_id FROM lca_define_topology_relation WHERE lca_define_lca_id = '".$lca_id."'");
		for($i = 0; $DBRESULT->fetchInto($topo); $i++)
			$lca["lca_topos"][$topo["topology_topology_id"]] = 1;
		$DBRESULT->free();
	}

if(!isset($lca["lca_topos"]))
$lca["lca_topos"] = array();

	# Init LCA 
	
	$lca_data = getLCAHostByID($pearDB);
	$lcaHostStr = getLCAHostStr($lca_data["LcaHost"]);
	$lcaHGStr = getLCAHGStr($lca_data["LcaHostGroup"]);
	$lca_sg = getLCASG($pearDB);
	$lcaSGStr = getLCASGStr($lca_sg);
	
	#
	## Database retrieve information for differents elements list we need on the page
	#
	# Host Groups comes from DB -> Store in $hgs Array
	$hgs = array();
	if ($oreon->user->admin || !HadUserLca($pearDB))
		$DBRESULT =& $pearDB->query("SELECT hg_id, hg_name FROM hostgroup ORDER BY hg_name");
	else
		$DBRESULT =& $pearDB->query("SELECT hg_id, hg_name FROM hostgroup WHERE hg_id IN (".$lcaHGStr.") ORDER BY hg_name");
	while($DBRESULT->fetchInto($hg))
		$hgs[$hg["hg_id"]] = $hg["hg_name"];
	$DBRESULT->free();
	#
	# Service Groups comes from DB -> Store in $sgs Array
	$sgs = array();
	if ($oreon->user->admin || !HadUserLca($pearDB))
		$DBRESULT =& $pearDB->query("SELECT sg_id, sg_name FROM servicegroup ORDER BY sg_name");
	else
		$DBRESULT =& $pearDB->query("SELECT sg_id, sg_name FROM servicegroup WHERE sg_id IN (".$lcaSGStr.") ORDER BY sg_name");
	while($DBRESULT->fetchInto($sg))
		$sgs[$sg["sg_id"]] = $sg["sg_name"];
	$DBRESULT->free();
	#
	# Host comes from DB -> Store in $hosts Array
	$hosts = array();
	if ($oreon->user->admin || !HadUserLca($pearDB))
		$DBRESULT =& $pearDB->query("SELECT host_id, host_name FROM host WHERE host_register = '1' ORDER BY host_name");
	else
		$DBRESULT =& $pearDB->query("SELECT host_id, host_name FROM host WHERE host_register = '1' AND host_id IN (".$lcaHostStr.") ORDER BY host_name");
	while($DBRESULT->fetchInto($host))
		$hosts[$host["host_id"]] = $host["host_name"];
	$DBRESULT->free();
	#
	# Contact Groups comes from DB -> Store in $cgs Array
	$cgs = array();
	$DBRESULT =& $pearDB->query("SELECT cg_id, cg_name FROM contactgroup ORDER BY cg_name");
	while($DBRESULT->fetchInto($cg))
		$cgs[$cg["cg_id"]] = $cg["cg_name"];
	$DBRESULT->free();
	#
	# End of "database-retrieved" information
	##########################################################
	##########################################################
	# Var information to format the element
	#
	$attrsText 		= array("size"=>"30");
	$attrsAdvSelect = array("style" => "width: 200px; height: 100px;");
	$attrsTextarea 	= array("rows"=>"3", "cols"=>"30");
	$template 		= "<table><tr><td>{unselected}</td><td align='center'>{add}<br><br><br>{remove}</td><td>{selected}</td></tr></table>";

	#
	## Form begin
	#
	$form = new HTML_QuickForm('Form', 'post', "?p=".$p);
	if ($o == "a")
		$form->addElement('header', 'title', $lang["lca_add"]);
	else if ($o == "c")
		$form->addElement('header', 'title', $lang["lca_change"]);
	else if ($o == "w")
		$form->addElement('header', 'title', $lang["lca_view"]);

	#
	## LCA basic information
	#
	$form->addElement('header', 'information', $lang['lca_infos']);
	$form->addElement('text', 'lca_name', $lang["lca_name"], $attrsText);
	$form->addElement('text', 'lca_alias', $lang["alias"], $attrsText);
/*	$tab = array();
	$tab[] = &HTML_QuickForm::createElement('radio', 'lca_type', null, $lang['lca_tpMenu'], '1');
	$tab[] = &HTML_QuickForm::createElement('radio', 'lca_type', null, $lang['lca_tpRes'], '2');
	$tab[] = &HTML_QuickForm::createElement('radio', 'lca_type', null, $lang['lca_tpBoth'], '3');
	$form->addGroup($tab, 'lca_type', $lang["lca_type"], '&nbsp;');
	$form->setDefaults(array('lca_type' => '3')); */
	$tab = array();
	$tab[] = &HTML_QuickForm::createElement('radio', 'lca_activate', null, $lang["enable"], '1');
	$tab[] = &HTML_QuickForm::createElement('radio', 'lca_activate', null, $lang["disable"], '0');
	$form->addGroup($tab, 'lca_activate', $lang["status"], '&nbsp;');
	$form->setDefaults(array('lca_activate' => '1'));

	#
	## Contact Group concerned
	#
	$form->addElement('header', 'cg', $lang['lca_appCG']);
    $ams1 =& $form->addElement('advmultiselect', 'lca_cgs', $lang["lca_cg"], $cgs, $attrsAdvSelect);
	$ams1->setButtonAttributes('add', array('value' =>  $lang['add']));
	$ams1->setButtonAttributes('remove', array('value' => $lang['delete']));
	$ams1->setElementTemplate($template);
	echo $ams1->getElementJs(false);

	#
	## Further informations
	#
	$form->addElement('header', 'furtherInfos', $lang['further_infos']);
	$form->addElement('textarea', 'lca_comment', $lang["lca_comment"], $attrsTextarea);
	
	#
	## Resources concerned
	#
	$form->addElement('header', 'rs', $lang['lca_appRes']);

    $ams1 =& $form->addElement('advmultiselect', 'lca_hgs', $lang["lca_hg"], $hgs, $attrsAdvSelect);
	$ams1->setButtonAttributes('add', array('value' =>  $lang['add']));
	$ams1->setButtonAttributes('remove', array('value' => $lang['delete']));
	$ams1->setElementTemplate($template);
	echo $ams1->getElementJs(false);
	$tab = array();
	$tab[] = &HTML_QuickForm::createElement('radio', 'lca_hg_childs', null, $lang["yes"], '1');
	$tab[] = &HTML_QuickForm::createElement('radio', 'lca_hg_childs', null, $lang["no"], '0');
	$form->addGroup($tab, 'lca_hg_childs', $lang['lca_hgChilds'], '&nbsp;');
	$form->setDefaults(array('lca_hg_childs' => '1'));

    $ams1 =& $form->addElement('advmultiselect', 'lca_hosts', $lang["lca_host"], $hosts, $attrsAdvSelect);
	$ams1->setButtonAttributes('add', array('value' =>  $lang['add']));
	$ams1->setButtonAttributes('remove', array('value' => $lang['delete']));
	$ams1->setElementTemplate($template);
	echo $ams1->getElementJs(false);

    $ams1 =& $form->addElement('advmultiselect', 'lca_sgs', $lang["lca_sg"], $sgs, $attrsAdvSelect);
	$ams1->setButtonAttributes('add', array('value' =>  $lang['add']));
	$ams1->setButtonAttributes('remove', array('value' => $lang['delete']));
	$ams1->setElementTemplate($template);
	echo $ams1->getElementJs(false);

	#
	## Topology concerned
	#
	$form->addElement('header', 'pages', $lang['lca_appTopo']);
	$rq = "SELECT topology_id, topology_page, topology_name, topology_parent FROM topology WHERE topology_parent IS NULL AND topology_page IN (".$oreon->user->lcaTStr.") ORDER BY topology_order";
	$DBRESULT1 =& $pearDB->query($rq);
	#
	$lca_topos = array();

	$lca_topos2 = array();
	$a = 0;
	while ($DBRESULT1->fetchInto($topo1))	{

		$lca_topos2[$a] = array();
		$lca_topos2[$a]["name"] = array_key_exists($topo1["topology_name"], $lang) ? "&nbsp;&nbsp;".$lang[$topo1["topology_name"]] : "&nbsp;&nbsp;#UNDEF#";
		$lca_topos2[$a]["id"] = $topo1["topology_id"];
		$lca_topos2[$a]["checked"] = array_key_exists($topo1["topology_id"],$lca["lca_topos"]) ? "true" : "false";
		$lca_topos2[$a]["c_id"] = $a;
		$lca_topos2[$a]["childs"] = array();

		/*old*/
	 	$lca_topos[] =  &HTML_QuickForm::createElement('checkbox', $topo1["topology_id"], null, array_key_exists($topo1["topology_name"], $lang) ? "&nbsp;&nbsp;".$lang[$topo1["topology_name"]]."<br>" : "&nbsp;&nbsp;#UNDEF#"."<br>", array("style"=>"margin-top: 5px;", "id"=>$topo1["topology_id"]));
	 	$rq = "SELECT topology_id, topology_page, topology_name, topology_parent FROM topology WHERE topology_parent = '".$topo1["topology_page"]."' AND topology_page IN (".$oreon->user->lcaTStr.") ORDER BY topology_order";
	 	$DBRESULT2 =& $pearDB->query($rq);
		/*old*/
		$b = 0;
		while ($DBRESULT2->fetchInto($topo2))	{
			$lca_topos2[$a]["childs"][$b] = array();
			$lca_topos2[$a]["childs"][$b]["name"] = array_key_exists($topo2["topology_name"], $lang) ? "&nbsp;&nbsp;".$lang[$topo2["topology_name"]] : "&nbsp;&nbsp;#UNDEF#";
			$lca_topos2[$a]["childs"][$b]["id"] = $topo2["topology_id"];
			$lca_topos2[$a]["childs"][$b]["checked"] = array_key_exists($topo2["topology_id"],$lca["lca_topos"]) ? "true" : "false";
			$lca_topos2[$a]["childs"][$b]["c_id"] = $a."_".$b;
			$lca_topos2[$a]["childs"][$b]["childs"] = array();



			/*old*/
		 	$lca_topos[] =  &HTML_QuickForm::createElement('checkbox', $topo2["topology_id"], NULL, array_key_exists($topo2["topology_name"], $lang) ? "&nbsp;&nbsp;".$lang[$topo2["topology_name"]]."<br>" : "&nbsp;&nbsp;#UNDEF#"."<br>", array("style"=>"margin-top: 5px; margin-left: 20px;"));
		 	$rq = "SELECT topology_id, topology_name, topology_parent, topology_page FROM topology WHERE topology_parent = '".$topo2["topology_page"]."' AND topology_page IN (".$oreon->user->lcaTStr.") ORDER BY topology_order";
		 	$DBRESULT3 =& $pearDB->query($rq);
			/*old*/
			$c = 0;
			while ($DBRESULT3->fetchInto($topo3)){
				$lca_topos2[$a]["childs"][$b]["childs"][$c] = array();
				$lca_topos2[$a]["childs"][$b]["childs"][$c]["name"] = array_key_exists($topo3["topology_name"], $lang) ? "&nbsp;&nbsp;".$lang[$topo3["topology_name"]] : "&nbsp;&nbsp;#UNDEF#";
				$lca_topos2[$a]["childs"][$b]["childs"][$c]["id"] = $topo3["topology_id"];
				$lca_topos2[$a]["childs"][$b]["childs"][$c]["checked"] = array_key_exists($topo3["topology_id"],$lca["lca_topos"]) ? "true" : "false";
				$lca_topos2[$a]["childs"][$b]["childs"][$c]["c_id"] = $a."_".$b."_".$c;
				$lca_topos2[$a]["childs"][$b]["childs"][$c]["childs"] = array();



				/*old*/
			 	$lca_topos[] =  &HTML_QuickForm::createElement('checkbox', $topo3["topology_id"], null, array_key_exists($topo3["topology_name"], $lang) ? "&nbsp;&nbsp;".$lang[$topo3["topology_name"]]."<br>" : "&nbsp;&nbsp;#UNDEF#"."<br>", array("style"=>"margin-top: 5px; margin-left: 40px;"));
				$rq = "SELECT topology_id, topology_name, topology_parent FROM topology WHERE topology_parent = '".$topo3["topology_page"]."' AND topology_page IN (".$oreon->user->lcaTStr.") ORDER BY topology_order";
			 	$DBRESULT4 =& $pearDB->query($rq);
				/*old*/
				$d = 0;
				while ($DBRESULT4->fetchInto($topo4)){
					$lca_topos2[$a]["childs"][$b]["childs"][$c]["childs"][$d] = array();
					$lca_topos2[$a]["childs"][$b]["childs"][$c]["childs"][$d]["name"] = array_key_exists($topo4["topology_name"], $lang) ? "&nbsp;&nbsp;".$lang[$topo4["topology_name"]] : "&nbsp;&nbsp;#UNDEF#";
					$lca_topos2[$a]["childs"][$b]["childs"][$c]["childs"][$d]["id"] = $topo4["topology_id"];
					$lca_topos2[$a]["childs"][$b]["childs"][$c]["childs"][$d]["checked"] = array_key_exists( $topo4["topology_id"],$lca["lca_topos"]) ? "true" : "false";
					$lca_topos2[$a]["childs"][$b]["childs"][$c]["childs"][$d]["c_id"] = $a."_".$b."_".$c."_".$d;
					$lca_topos2[$a]["childs"][$b]["childs"][$c]["childs"][$d]["childs"] = array();



					/*old*/
				 	$lca_topos[] =  &HTML_QuickForm::createElement('checkbox', $topo4["topology_id"], null, array_key_exists($topo4["topology_name"], $lang) ? "&nbsp;&nbsp;".$lang[$topo4["topology_name"]]."<br>" : "&nbsp;&nbsp;#UNDEF#"."<br>", array("style"=>"margin-top: 5px; margin-left: 55px;"));
					/*old*/					
					$d++;
				}
				$c++;		
			}
			$b++;
		}
		$a++;
	}
	

	if ($o == "a")	{
		function one($v)	{
			$v->setValue(1);
			return $v;
		}
		$lca_topos = array_map("one", $lca_topos);
	}
	$form->addGroup($lca_topos, 'lca_topos', $lang['lca_topo'], '&nbsp;&nbsp;');

	$tab = array();
	$tab[] = &HTML_QuickForm::createElement('radio', 'action', null, $lang['actionList'], '1');
	$tab[] = &HTML_QuickForm::createElement('radio', 'action', null, $lang['actionForm'], '0');
	$form->addGroup($tab, 'action', $lang["action"], '&nbsp;');
	$form->setDefaults(array('action'=>'1'));


	$form->addElement('hidden', 'lca_id');
	$redirect =& $form->addElement('hidden', 'o');
	$redirect->setValue($o);

	#
	## Form Rules
	#
	$form->applyFilter('__ALL__', 'myTrim');
	$form->addRule('lca_name', $lang['ErrName'], 'required');
	$form->registerRule('exist', 'callback', 'testExistence');
	$form->addRule('lca_name', $lang['ErrAlreadyExist'], 'exist');
	$form->setRequiredNote($lang['requiredFields']);

	#
	##End of form definition
	#

	# Smarty template Init
	$tpl = new Smarty();
	$tpl = initSmartyTpl($path, $tpl);

	# Just watch a LCA information
	if ($o == "w")	{
		$form->addElement("button", "change", $lang['modify'], array("onClick"=>"javascript:window.location.href='?p=".$p."&o=c&lca_id=".$lca_id."'"));
	    $form->setDefaults($lca);
		$form->freeze();
	}
	# Modify a LCA information
	else if ($o == "c")	{
		$subC =& $form->addElement('submit', 'submitC', $lang["save"]);
		$res =& $form->addElement('reset', 'reset', $lang["delete"]);
	    $form->setDefaults($lca);
	}
	# Add a LCA information
	else if ($o == "a")	{
		$subA =& $form->addElement('submit', 'submitA', $lang["save"]);
		$res =& $form->addElement('reset', 'reset', $lang["delete"]);
	}
	$tpl->assign('msg', array ("changeL"=>"?p=".$p."&o=c&lca_id=".$lca_id, "changeT"=>$lang['modify']));


	$tpl->assign("lca_topos2", $lca_topos2);

	$tpl->assign("sort1", $lang['lca_infos']);
	$tpl->assign("sort2", $lang['lca_sortRes']);
	$tpl->assign("sort3", $lang['lca_sortTopo']);

	$valid = false;
	if ($form->validate())	{
		$lcaObj =& $form->getElement('lca_id');
		if ($form->getSubmitValue("submitA"))
			$lcaObj->setValue(insertLCAInDB());
		else if ($form->getSubmitValue("submitC"))
			updateLCAInDB($lcaObj->getValue());
		$o = NULL;
		$form->addElement("button", "change", $lang['modify'], array("onClick"=>"javascript:window.location.href='?p=".$p."&o=c&lca_id=".$lcaObj->getValue()."'"));
		$form->freeze();
		$valid = true;
	}
	$action = $form->getSubmitValue("action");
	if ($valid && $action["action"]["action"])
		require_once("listLCA.php");
	else	{
		#Apply a template definition
		$renderer =& new HTML_QuickForm_Renderer_ArraySmarty($tpl);
		$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
		$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
		$form->accept($renderer);
		$tpl->assign('form', $renderer->toArray());
		$tpl->assign('o', $o);
		$tpl->display("formLCA.ihtml");
	}
/*
	echo "<pre>";
	print_r($lca_topos2);	
	echo "</pre>";
*/

?>