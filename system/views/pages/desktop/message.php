<?php
# background paralax
echo '<div id="background-paralax" class="message"></div>';

# inclusion des elements
include 'defaultElement/subnav.php';
include 'defaultElement/movers.php';

# contenu spécifique
echo '<div id="content">';
	# inclusion des modules
	include_once HERMES;

	# loading des objets
	ASM::$ntm->load(array('rPlayer' => CTR::$data->get('playerId')), array('dSending', 'DESC'));
	ASM::$msm->loadByRequest(
		'WHERE (rPlayerWriter = ? OR rPlayerReader = ?) ORDER BY dSending DESC',
		array(CTR::$data->get('playerId'), CTR::$data->get('playerId'))
	);

	# lastNotif component
	$notification_lastNotif = array();
	$notification_archivedNotif = array();
	for ($i = 0; $i < ASM::$ntm->size(); $i++) {
		if (!ASM::$ntm->get($i)->getArchived()) {
			$notification_lastNotif[$i] = ASM::$ntm->get($i);
		} else {
			$notification_archivedNotif[$i] = ASM::$ntm->get($i);
		}
	}
	include COMPONENT . 'notif/lastNotif.php';

	if (count($notification_archivedNotif) > 0) {
		include COMPONENT . 'notif/archivedNotif.php';
	}

	# newMessage component
	include COMPONENT . 'message/newMessage.php';

	# affichage des messages
	if (ASM::$msm->size() > 0) {
		$thread = array();
		for ($i = 0; $i < ASM::$msm->size(); $i++) {
			$message = ASM::$msm->get($i);
			if ($message->getRPlayerWriter() !== CTR::$data->get('playerId')) {
				$message->setReaded(1);
			}
			if (!in_array($message->getThread(), array_keys($thread))) {
				$thread[$message->getThread()] = $message->getDSending();
			} else {
				if (strtotime($thread[$message->getThread()]) < strtotime($message->getDSending())) {
					$thread[$message->getThread()] = $message->getDSending();
				}
			}
		}
		uasort($thread, function($a, $b) {
			if (strtotime($a) == strtotime($b)) { return 0; }
			return (strtotime($a) > strtotime($b)) ? -1 : 1;
		});
		
		# thread component
		$j = 0;
		foreach ($thread as $k => $v) {
			$j++;
			$threadId_thread = $k;
			$lastMessage_thread = $v;
			$messages_thread = array();
			for ($i = 0; $i < ASM::$msm->size(); $i++) {
				if (ASM::$msm->get($i)->getThread() == $k) {
					$messages_thread[] = ASM::$msm->get($i);
				}
			}
			include COMPONENT . 'message/thread.php';

			if ($j > MSM_STEPTHREAD - 1) {
				include COMPONENT . 'message/moreThread.php';
				break;
			}
		}
	} else {
		# noThread component
		include COMPONENT . 'message/noThread.php';
	}
echo '</div>';
?>