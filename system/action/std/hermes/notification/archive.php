<?php
# archive or unarchive action
# int id 			id de la notif

include_once HERMES;

if (CTR::$get->exist('id')) {
	$id = CTR::$get->get('id');
} else if (CTR::$post->exist('id')) {
	$id = CTR::$post->get('id');
} else {
	$id = FALSE;
}

if ($id) {
	$S_NTM1 = ASM::$ntm->getCurrentSession();
	ASM::$ntm->newSession(ASM_UMODE);
	ASM::$ntm->load(array('id' => $id));
	$notif = ASM::$ntm->get();
	if ($notif->getArchived() == 0) {
		$notif->setArchived(1);
	} else {
		$notif->setArchived(0);
	}
	ASM::$ntm->changeSession($S_NTM1);
} else {
	CTR::$alert->add('cette notification n\'existe pas', ALERT_STD_ERROR);
}
