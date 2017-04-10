<?php

/**
 * Place Manager
 *
 * @author Jacky Casas
 * @copyright Expansion - le jeu
 *
 * @package Gaia
 * @update 20.05.13
*/
namespace Asylamba\Modules\Gaia\Manager;

use Asylamba\Classes\Entity\EntityManager;
use Asylamba\Classes\Library\Utils;
use Asylamba\Classes\Library\Game;
use Asylamba\Classes\Library\Format;
use Asylamba\Classes\Container\Session;
use Asylamba\Modules\Gaia\Resource\SquadronResource;

use Asylamba\Classes\Exception\ErrorException;

use Asylamba\Classes\Worker\CTC;
use Asylamba\Modules\Ares\Manager\CommanderManager;
use Asylamba\Modules\Ares\Manager\FightManager;
use Asylamba\Modules\Ares\Manager\ReportManager;
use Asylamba\Modules\Zeus\Manager\PlayerManager;
use Asylamba\Modules\Zeus\Manager\PlayerBonusManager;
use Asylamba\Modules\Athena\Manager\OrbitalBaseManager;
use Asylamba\Modules\Athena\Manager\CommercialRouteManager;
use Asylamba\Modules\Demeter\Manager\ColorManager;
use Asylamba\Modules\Athena\Manager\RecyclingMissionManager;
use Asylamba\Modules\Hermes\Manager\NotificationManager;

use Asylamba\Modules\Gaia\Model\Place;
use Asylamba\Modules\Athena\Model\OrbitalBase;
use Asylamba\Modules\Ares\Model\Commander;
use Asylamba\Modules\Zeus\Model\PlayerBonus;
use Asylamba\Modules\Ares\Model\LiveReport;
use Asylamba\Modules\Ares\Model\Report;
use Asylamba\Modules\Hermes\Model\Notification;
use Asylamba\Modules\Demeter\Model\Color;
use Asylamba\Modules\Gaia\Model\System;

class PlaceManager {
	/** @var EntityManager **/
	protected $entityManager;
	/** @var CommanderManager **/
	protected $commanderManager;
	/** @var FightManager **/
	protected $fightManager;
	/** @var ReportManager */
	protected $reportManager;
	/** @var PlayerManager **/
	protected $playerManager;
	/** @var PlayerBonusManager **/
	protected $playerBonusManager;
	/** @var OrbitalBaseManager **/
	protected $orbitalBaseManager;
	/** @var CommercialRouteManager **/
	protected $commercialRouteManager;
	/** @var ColorManager **/
	protected $colorManager;
	/** @var RecyclingMissionManager **/
	protected $recyclingMissionManager;
	/** @var NotificationManager **/
	protected $notificationManager;
	/** @var CTC **/
	protected $ctc;
	/** @var Session **/
	protected $session;
	
	/**
	 * @param EntityManager $entityManager
	 * @param CommanderManager $commanderManager
	 * @param FightManager $fightManager
	 * @param ReportManager $reportManager
	 * @param PlayerManager $playerManager
	 * @param PlayerBonusManager $playerBonusManager
	 * @param OrbitalBaseManager $orbitalBaseManager
	 * @param CommercialRouteManager $commercialRouteManager
	 * @param ColorManager $colorManager
	 * @param RecyclingMissionManager $recyclingMissionManager
	 * @param NotificationManager $notificationManager
	 * @param CTC $ctc
	 * @param Session $session
	 */
	public function __construct(
		EntityManager $entityManager,
		CommanderManager $commanderManager,
		FightManager $fightManager,
		ReportManager $reportManager,
		PlayerManager $playerManager,
		PlayerBonusManager $playerBonusManager,
		OrbitalBaseManager $orbitalBaseManager,
		CommercialRouteManager $commercialRouteManager,
		ColorManager $colorManager,
		RecyclingMissionManager $recyclingMissionManager,
		NotificationManager $notificationManager,
		CTC $ctc,
		Session $session
	) {
		$this->entityManager = $entityManager;
		$this->commanderManager = $commanderManager;
		$this->fightManager = $fightManager;
		$this->reportManager = $reportManager;
		$this->playerManager = $playerManager;
		$this->playerBonusManager = $playerBonusManager;
		$this->orbitalBaseManager = $orbitalBaseManager;
		$this->commercialRouteManager = $commercialRouteManager;
		$this->colorManager = $colorManager;
		$this->recyclingMissionManager = $recyclingMissionManager;
		$this->notificationManager = $notificationManager;
		$this->ctc = $ctc;
		$this->session = $session;
	}
	
	/**
	 * @param int $id
	 * @return Place
	 */
	public function get($id)
	{
		$place = $this->entityManager->getRepository(Place::class)->get($id);
		
		if($place === null) {
			return null;
		}
		
		$this->uMethod($place);
		
		return $place;
	}
	
	/**
	 * @param int $ids
	 */
	public function getByIds($ids = [])
	{
		$places = $this->entityManager->getRepository(Place::class)->getByIds($ids);
		
		foreach ($places as $place) {
			$this->uMethod($place);
		}
		
		return $places;
	}
	
	public function getSystemPlaces(System $system)
	{
		$places = $this->entityManager->getRepository(Place::class)->getSystemPlaces($system->getId());
		
		foreach ($places as $place) {
			$this->uMethod($place);
		}
		
		return $places;
	}

	public function search($search, $order = array(), $limit = array()) {
//		$search = '%' . $search . '%';
//			WHERE (pl.statement = 1 OR pl.statement = 2 OR pl.statement = 3)
//			AND (LOWER(pl.name) LIKE LOWER(?)
//			OR   LOWER(ob.name) LIKE LOWER(?))
	}

	protected function fill(Place $place) {
		$place->commanders = $this->commanderManager->getBaseCommanders($place->getId(), [Commander::AFFECTED, Commander::MOVING]);

		$this->uMethod($place);
	}
	
	public function uMethod(Place $place) {
		$token = $this->ctc->createContext('place');
		$now   = Utils::now();
		
		if (Utils::interval($place->uPlace, $now, 's') > 0) {
			# update time
			$hours = Utils::intervalDates($now, $place->uPlace);
			$place->uPlace = $now;

			# RESOURCE
			if ($place->typeOfBase == Place::TYP_EMPTY && $place->typeOfPlace == Place::TERRESTRIAL) {
				foreach ($hours as $hour) {
					$this->ctc->add($hour, $this, 'uResources', $place, array($place));
				}
			}

			if ($place->rPlayer == NULL) {
				foreach ($hours as $hour) {
					$this->ctc->add($hour, $this, 'uDanger', $place, array($place));
				}
			}
			$commanders = $this->commanderManager->getINcomingCommanders($place->id);

			if (count($commanders) > 0) {
				$placeIds = array();
				$playerBonuses = array();

				foreach ($commanders as $commander) { 
					# fill the places
					$placeIds[] = $commander->getRBase();

					# fill & load the bonuses if needed
					if (!array_key_exists($commander->rPlayer, $playerBonuses)) {
						
						$bonus = $this->playerBonusManager->getBonusByPlayer($commander->rPlayer);
						$this->playerBonusManager->load($bonus);
						$playerBonuses[$commander->rPlayer] = $bonus;
					}
				}

				# load all the places at the same time
				$places = $this->getByIds($placeIds);

				# load annexes components
				foreach ($commanders as $commander) { 
					# only if the commander isn't in travel
					$hasntU = TRUE;
					if ($commander->uMethodCtced) {
						$hasntU = FALSE;

						if (Utils::interval($commander->lasrUMethod, Utils::now(), 's') > 10) {
							$hasntU = TRUE;
						}
					}
					if ($commander->dArrival <= $now and $hasntU) {
						switch ($commander->travelType) {
							case Commander::MOVE: 
								$commanderPlace = $this->get($commander->rBase);
								$bonus = $playerBonuses[$commander->rPlayer];
								
								if ($this->ctc->add($commander->dArrival, $this, 'uChangeBase', $place, [$place, $commander, $commanderPlace, $bonus])) {
									$commander->uMethodCtced = TRUE;
									$commander->lastUMethod = Utils::now();
								}
							break;

							case Commander::LOOT: 
								$commanderPlace = $this->get($commander->rBase);
								$bonus = $playerBonuses[$commander->rPlayer];

								$commanderPlayer = $this->playerManager->get($commander->rPlayer);

								if ($place->rPlayer != NULL) {
									$placePlayer = $this->playerManager->get($place->rPlayer);
									$placeBase = $this->orbitalBaseManager->get($place->id);
								} else {
									$placePlayer = NULL;
									$placeBase = NULL;
								}

								$commanderColor = $this->colorManager->get($commander->playerColor);

								if ($this->ctc->add($commander->dArrival, $this, 'uLoot', $place, array($place, $commander, $commanderPlace, $bonus, $commanderPlayer, $placePlayer, $placeBase, $commanderColor))) {
									$commander->uMethodCtced = TRUE;
									$commander->lastUMethod = Utils::now();
								}
							break;

							case Commander::COLO: 
								$commanderPlace = $this->get($commander->rBase);
								$bonus = $playerBonuses[$commander->rPlayer];

								$commanderPlayer = $this->playerManager->get($commander->rPlayer);

								if ($place->rPlayer != NULL) {
									$placePlayer = $this->playerManager->get($place->rPlayer);

									$placeBase = $this->orbitalBaseManager->get($place->id);

									$S_REM_C1 = $this->recyclingMissionManager->getCurrentSession();
									$this->recyclingMissionManager->newSession();
									$this->recyclingMissionManager->load(array('rBase' => $place->id));
									$S_REM_C2 = $this->recyclingMissionManager->getCurrentSession();
									$this->recyclingMissionManager->changeSession($S_REM_C1);

									$baseCommanders = $this->commanderManager->getBaseCommanders($place->id);

								} else {
									$placePlayer = NULL;
									$placeBase = NULL;
									$S_REM_C2 = NULL;
									$baseCommanders = [];
								}

								$commanderColor = $this->colorManager->get($commander->playerColor);
								
								if ($this->ctc->add($commander->dArrival, $this, 'uConquer', $place, array($place, $commander, $commanderPlace, $bonus, $commanderPlayer, $placePlayer, $placeBase, $commanderColor, $S_REM_C2, $baseCommanders))) {
									$commander->uMethodCtced = TRUE;
									$commander->lastUMethod = Utils::now();
								}
							break;

							case Commander::BACK: 
								$base = $this->orbitalBaseManager->get($commander->getRBase());
								
								if ($this->ctc->add($commander->dArrival, $this, 'uComeBackHome', $place, array($place, $commander, $base))) {
									$commander->uMethodCtced = TRUE;
									$commander->lastUMethod = Utils::now();
								}
							break;

							default: throw new ErrorException("L'action {$commander->travelType} n'existe pas.");
						}
					}
				}
			}
		}
		$this->entityManager->flush();
		$this->ctc->applyContext($token);
	}

	public function uDanger(Place $place) {
		$place->danger += Place::REPOPDANGER;

		if ($place->danger > $place->maxDanger) {
			$place->danger = $place->maxDanger;
		}
	}

	public function uResources(Place $place) {
		$maxResources = ceil($place->population / Place::COEFFPOPRESOURCE) * Place::COEFFMAXRESOURCE * ($place->maxDanger + 1);
		$place->resources += floor(Place::COEFFRESOURCE * $place->population);

		if ($place->resources > $maxResources) {
			$place->resources = $maxResources;
		}
	}

	/**
	 * Fleet moving
	 * 
	 * @param Place $place
	 * @param Commander $commander
	 * @param Place $commanderPlace
	 * @param PlayerBonus $playerBonus
	 */
	public function uChangeBase(Place $place, Commander $commander, Place $commanderPlace, PlayerBonus $playerBonus) {
		# si la place et la flotte ont la même couleur
		# on pose la flotte si il y a assez de place
		# sinon on met la flotte dans les hangars
		if ($place->playerColor == $commander->playerColor AND $place->typeOfBase == 4) {
			$maxCom = ($place->typeOfOrbitalBase == OrbitalBase::TYP_MILITARY || $place->typeOfOrbitalBase == OrbitalBase::TYP_CAPITAL)
				? OrbitalBase::MAXCOMMANDERMILITARY
				: OrbitalBase::MAXCOMMANDERSTANDARD;

			# si place a assez de case libre :
			if (count($place->commanders) < $maxCom) {
				$comLine2 = 0;

				foreach ($place->commanders as $com) {
					if ($com->line == 2) {
						$comLine2++;
					}
				}

				if ($maxCom == OrbitalBase::MAXCOMMANDERMILITARY) {
					if ($comLine2 < 2) {
						$commander->line = 2;
					} else {
						$commander->line = 1;
					}
				} else {
					if ($comLine2 < 1) {
						$commander->line = 2;
					} else {
						$commander->line = 1;
					}
				}

				# changer rBase commander
				$commander->rBase = $place->id;
				$commander->travelType = NULL;
				$commander->statement = Commander::AFFECTED;

				# ajouter à la place le commandant
				$place->commanders[] = $commander;

				# envoi de notif
				$this->sendNotif($place, Place::CHANGESUCCESS, $commander);
			} else {
				# changer rBase commander
				$commander->rBase = $place->id;
				$commander->travelType = NULL;
				$commander->statement = Commander::RESERVE;

				$this->commanderManager->emptySquadrons($commander);

				# envoi de notif
				$this->sendNotif($place, Place::CHANGEFAIL, $commander);
			}

			# modifier le rPlayer (ne se modifie pas si c'est le même)
			$commander->rPlayer = $place->rPlayer;

			# instance de la place d'envoie + suppr commandant de ses flottes
			# enlever à rBase le commandant
			for ($i = 0; $i < count($commanderPlace->commanders); $i++) {
				if ($commanderPlace->commanders[$i]->id == $commander->id) {
					unset($commanderPlace->commanders[$i]);
					$commanderPlace->commanders = array_merge($commanderPlace->commanders);
				}
			}
		} else {
			# retour forcé
			$this->comeBack($place, $commander, $commanderPlace, $playerBonus);
			$this->sendNotif($place, Place::CHANGELOST, $commander);
		}
	}

	# pillage
	public function uLoot(Place $place, Commander $commander, $commanderPlace, $playerBonus, $commanderPlayer, $placePlayer, $placeBase, $commanderColor) {
		LiveReport::$type   = Commander::LOOT;
		LiveReport::$dFight = $commander->dArrival;

		# si la planète est vide
		if ($place->rPlayer == NULL) {
			LiveReport::$isLegal = Report::LEGAL;

			$commander->travelType = NULL;
			$commander->travelLength = NULL;

			# planète vide : faire un combat
			$this->startFight($place, $commander, $commanderPlayer);

			# victoire
			if ($commander->getStatement() != Commander::DEAD) {
				# piller la planète
				$this->lootAnEmptyPlace($place, $commander, $playerBonus);
				# création du rapport de combat
				$report = $this->createReport($place);

				# réduction de la force de la planète
				$percentage = (($report->pevAtEndD + 1) / ($report->pevInBeginD + 1)) * 100;
				$place->danger = round(($percentage * $place->danger) / 100);

				$this->comeBack($place, $commander, $commanderPlace, $playerBonus);
				$this->sendNotif($place, Place::LOOTEMPTYSSUCCESS, $commander, $report->id);
			} else {
				# si il est mort
				# enlever le commandant de la session
				for ($i = 0; $i < count($commanderPlace->commanders); $i++) {
					if ($commanderPlace->commanders[$i]->getId() == $commander->getId()) {
						unset($commanderPlace->commanders[$i]);
						$commanderPlace->commanders = array_merge($commanderPlace->commanders);
					}
				}

				# création du rapport de combat
				$report = $this->createReport($place);
				$this->sendNotif($place, Place::LOOTEMPTYFAIL, $commander, $report->id);

				# réduction de la force de la planète
				$percentage = (($report->pevAtEndD + 1) / ($report->pevInBeginD + 1)) * 100;
				$place->danger = round(($percentage * $place->danger) / 100);
			}
		# si il y a une base d'un joueur
		} else {
			if ($commanderColor->colorLink[$place->playerColor] == Color::ALLY || $commanderColor->colorLink[$place->playerColor] == Color::PEACE) {
				LiveReport::$isLegal = Report::ILLEGAL;
			} else {
				LiveReport::$isLegal = Report::LEGAL;
			}

			# planète à joueur : si $this->rColor != commandant->rColor
			# si il peut l'attaquer
			if (($place->playerColor != $commander->getPlayerColor() && $place->playerLevel > 1 && $commanderColor->colorLink[$place->playerColor] != Color::ALLY) || ($place->playerColor == 0)) {
				$commander->travelType = NULL;
				$commander->travelLength = NULL;

				$dCommanders = array();
				foreach ($place->commanders AS $dCommander) {
					if ($dCommander->statement == Commander::AFFECTED && $dCommander->line == 1) {
						$dCommanders[] = $dCommander;
					}
				}

				# il y a des commandants en défense : faire un combat avec un des commandants
				if (count($dCommanders) != 0) {
					$aleaNbr = rand(0, count($dCommanders) - 1);
					$this->startFight($place, $commander, $commanderPlayer, $dCommanders[$aleaNbr], $placePlayer, TRUE);

					# victoire
					if ($commander->getStatement() != Commander::DEAD) {
						# piller la planète
						$this->lootAPlayerPlace($commander, $playerBonus, $placeBase);
						$this->comeBack($place, $commander, $commanderPlace, $playerBonus);
	
						# suppression des commandants						
						unset($place->commanders[$aleaNbr]);
						$place->commanders = array_merge($place->commanders);

						# création du rapport
						$report = $this->createReport($place);

						$this->sendNotif($place, Place::LOOTPLAYERWHITBATTLESUCCESS, $commander, $report->id);
				
					# défaite
					} else {
						# enlever le commandant de la session
						for ($i = 0; $i < count($commanderPlace->commanders); $i++) {
							if ($commanderPlace->commanders[$i]->getId() == $commander->getId()) {
								unset($commanderPlace->commanders[$i]);
								$commanderPlace->commanders = array_merge($commanderPlace->commanders);
							}
						}

						# création du rapport
						$report = $this->createReport($place);

						# mise à jour des flottes du commandant défenseur
						for ($j = 0; $j < count($dCommanders[$aleaNbr]->armyAtEnd); $j++) {
							for ($i = 0; $i < 12; $i++) { 
								$dCommanders[$aleaNbr]->armyInBegin[$j][$i] = $dCommanders[$aleaNbr]->armyAtEnd[$j][$i];
							}
						}

						$this->sendNotif($place, Place::LOOTPLAYERWHITBATTLEFAIL, $commander, $report->id);
					}
				} else {
					$this->lootAPlayerPlace($commander, $playerBonus, $placeBase);
					$this->comeBack($place, $commander, $commanderPlace, $playerBonus);
					$this->sendNotif($place, Place::LOOTPLAYERWHITOUTBATTLESUCCESS, $commander);
				}

			} else {
				# si c'est la même couleur
				if ($place->rPlayer == $commander->rPlayer) {
					# si c'est une de nos planètes
					# on tente de se poser
					$this->uChangeBase($place, $commander, $commanderPlace, $playerBonus);
				} else {
					# si c'est une base alliée
					# on repart
					$this->comeBack($place, $commander, $commanderPlace, $playerBonus);
					$this->sendNotif($place, Place::CHANGELOST, $commander);
				}
			}
		}
	}

	# conquest
	public function uConquer(Place $place, Commander $commander, $commanderPlace, $playerBonus, $commanderPlayer, $placePlayer, $placeBase, $commanderColor, $recyclingSession, $baseCommanders) {
		
		# conquete
		if ($place->rPlayer != NULL) {
			$commander->travelType = NULL;
			$commander->travelLength = NULL;

			if (($place->playerColor != $commander->getPlayerColor() && $place->playerLevel > 3 && $commanderColor->colorLink[$place->playerColor] != Color::ALLY) || ($place->playerColor == 0)) {
				$tempCom = array();

				for ($i = 0; $i < count($place->commanders); $i++) {
					if ($place->commanders[$i]->line <= 1) {
						$tempCom[] = $place->commanders[$i];
					}
				}
				for ($i = 0; $i < count($place->commanders); $i++) {
					if ($place->commanders[$i]->line >= 2) {
						$tempCom[] = $place->commanders[$i];
					}
				}

				$place->commanders = $tempCom;

				$nbrBattle = 0;
				$reportIds   = array();
				$reportArray = array();

				while ($nbrBattle < count($place->commanders)) {
					if ($place->commanders[$nbrBattle]->statement == Commander::AFFECTED) {
						LiveReport::$type = Commander::COLO;
						LiveReport::$dFight = $commander->dArrival;

						if ($commanderColor->colorLink[$place->playerColor] == Color::ALLY || $commanderColor->colorLink[$place->playerColor] == Color::PEACE) {
							LiveReport::$isLegal = Report::ILLEGAL;
						} else {
							LiveReport::$isLegal = Report::LEGAL;
						}

						$this->startFight($place, $commander, $commanderPlayer, $place->commanders[$nbrBattle], $placePlayer, TRUE);

						$report = $this->createReport($place);
						$reportArray[] = $report;
						$reportIds[] = $report->id;
						
						# PATCH DEGUEU POUR LES MUTLIS-COMBATS
						$reports = $this->reportManager->getByAttackerAndPlace($commander->rPlayer, $place->id, $commander->dArrival);
						foreach($reports as $r) {
							if ($r->id == $report->id) {
								continue;
							}
							$r->statementAttacker = Report::DELETED;
							$r->statementDefender = Report::DELETED;
						}
						$this->entityManager->flush(Report::class);
						########################################

						# mettre à jour armyInBegin si prochain combat pour prochain rapport
						for ($j = 0; $j < count($commander->armyAtEnd); $j++) {
							for ($i = 0; $i < 12; $i++) { 
								$commander->armyInBegin[$j][$i] = $commander->armyAtEnd[$j][$i];
							}
						}
						for ($j = 0; $j < count($place->commanders[$nbrBattle]->armyAtEnd); $j++) {
							for ($i = 0; $i < 12; $i++) {
								$place->commanders[$nbrBattle]->armyInBegin[$j][$i] = $place->commanders[$nbrBattle]->armyAtEnd[$j][$i];
							}
						}
						
						$nbrBattle++;
						# mort du commandant
						# arrêt des combats
						if ($commander->getStatement() == Commander::DEAD) {
							break;
						}
					} else {
						$nbrBattle++;
					}
				}

				# victoire
				if ($commander->getStatement() != Commander::DEAD) {
					if ($nbrBattle == 0) {
						$this->sendNotif($place, Place::CONQUERPLAYERWHITOUTBATTLESUCCESS, $commander, NULL);
					} else {
						$this->sendNotifForConquest($place, Place::CONQUERPLAYERWHITBATTLESUCCESS, $commander, $reportIds);
					}


					#attribuer le joueur à la place
					$place->commanders = array();
					$place->playerColor = $commander->playerColor;
					$place->rPlayer = $commander->rPlayer;

					# changer l'appartenance de la base (et de la place)
					$this->orbitalBaseManager->changeOwnerById($place->id, $placeBase, $commander->getRPlayer(), $recyclingSession, $baseCommanders);
					$place->commanders[] = $commander;

					$commander->rBase = $place->id;
					$commander->statement = Commander::AFFECTED;
					$commander->line = 2;

					# PATCH DEGUEU POUR LES MUTLIS-COMBATS
					$_NTM465 = $this->notificationManager->getCurrentSession();
					$this->notificationManager->newSession(TRUE);
					$this->notificationManager->load(['rPlayer' => $commander->rPlayer, 'dSending' => $commander->dArrival]);
					$this->notificationManager->load(['rPlayer' => $place->rPlayer, 'dSending' => $commander->dArrival]);
					if ($this->notificationManager->size() > 2) {
						for ($i = 0; $i < $this->notificationManager->size() - 2; $i++) {
							$this->notificationManager->deleteById($this->notificationManager->get($i)->id);
						}
					}
					$this->notificationManager->changeSession($_NTM465);
					######################################33

				# défaite
				} else {
					for ($i = 0; $i < count($place->commanders); $i++) {
						if ($place->commanders[$i]->statement == Commander::DEAD) {
							unset($place->commanders[$i]);
							$place->commanders = array_merge($place->commanders);
						}
					}

					$this->sendNotifForConquest($place, Place::CONQUERPLAYERWHITBATTLEFAIL, $commander, $reportIds);
				}

			} else {
				# si c'est la même couleur
				if ($place->rPlayer == $commander->rPlayer) {
					# si c'est une de nos planètes
					# on tente de se poser
					$this->uChangeBase($place, $commander, $commanderPlace, $playerBonus);
				} else {
					# si c'est une base alliée
					# on repart
					$this->comeBack($place, $commander, $commanderPlace, $playerBonus);
					$this->sendNotif($place, Place::CHANGELOST, $commander);
				}
			}

		# colonisation
		} else {
			$commander->travelType = NULL;
			$commander->travelLength = NULL;

			# faire un combat
			LiveReport::$type = Commander::COLO;
			LiveReport::$dFight = $commander->dArrival;
			LiveReport::$isLegal = Report::LEGAL;

			$this->startFight($place, $commander, $commanderPlayer);

			# victoire
			if ($commander->getStatement() !== Commander::DEAD) {
				# attribuer le rPlayer à la Place !
				$place->rPlayer = $commander->rPlayer;
				$place->commanders[] = $commander;
				$place->playerColor = $commander->playerColor;
				$place->typeOfBase = 4; 

				# créer une base
				$ob = new OrbitalBase();
				$ob->rPlace = $place->id;
				$ob->setRPlayer($commander->getRPlayer());
				$ob->setName('colonie');
				$ob->iSchool = 500;
				$ob->iAntiSpy = 500;
				$ob->resourcesStorage = 2000;
				$ob->uOrbitalBase = Utils::now();
				$ob->dCreation = Utils::now();
				$this->orbitalBaseManager->updatePoints($ob);

				$this->orbitalBaseManager->add($ob);

				# attibuer le commander à la place
				$commander->rBase = $place->id;
				$commander->statement = Commander::AFFECTED;
				$commander->line = 2;

				# ajout de la place en session
				if ($this->session->get('playerId') == $commander->getRPlayer()) {
					$this->session->addBase('ob', 
						$ob->getId(), 
						$ob->getName(), 
						$place->rSector, 
						$place->rSystem,
						'1-' . Game::getSizeOfPlanet($place->population),
						OrbitalBase::TYP_NEUTRAL);
				}
				
				# création du rapport
				$report = $this->createReport($place);

				$place->danger = 0;

				$this->sendNotif($place, Place::CONQUEREMPTYSSUCCESS, $commander, $report->id);
			
			# défaite
			} else {
				# création du rapport
				$report = $this->createReport($place);

				# mise à jour du danger
				$percentage = (($report->pevAtEndD + 1) / ($report->pevInBeginD + 1)) * 100;
				$place->danger = round(($percentage * $place->danger) / 100);

				$this->sendNotif($place, Place::CONQUEREMPTYFAIL, $commander);

				# enlever le commandant de la place
				for ($i = 0; $i < count($commanderPlace->commanders); $i++) {
					if ($commanderPlace->commanders[$i]->getId() == $commander->getId()) {
						unset($commanderPlace->commanders[$i]);
						$commanderPlace->commanders = array_merge($commanderPlace->commanders);
					}
				}
			}
		}
	}

	# retour à la maison
	public function uComeBackHome(Place $place, $commander, $commanderBase) {
		$commander->travelType = NULL;
		$commander->travelLength = NULL;
		$commander->dArrival = NULL;

		$commander->statement = Commander::AFFECTED;

		$this->sendNotif($place, Place::COMEBACK, $commander);

		if ($commander->resources > 0) {
			$this->orbitalBaseManager->increaseResources($commanderBase, $commander->resources, TRUE);
			$commander->resources = 0;
		}
	}

	# HELPER

	# comeBack
	public function comeBack(Place $place, $commander, $commanderPlace, $playerBonus) {
		$length   = Game::getDistance($place->getXSystem(), $commanderPlace->getXSystem(), $place->getYSystem(), $commanderPlace->getYSystem());
		$duration = Game::getTimeToTravel($commanderPlace, $place, $playerBonus->bonus);

		$commander->startPlaceName = $place->baseName;
		$commander->destinationPlaceName = $commander->oBName;
		$this->commanderManager->move($commander, $commander->rBase, $place->id, Commander::BACK, $length, $duration);
	}

	private function lootAnEmptyPlace(Place $place, $commander, $playerBonus) {
		$bonus = ($commander->rPlayer != $this->session->get('playerId'))
			? $playerBonus->bonus->get(PlayerBonus::SHIP_CONTAINER)
			: $this->session->get('playerBonus')->get(PlayerBonus::SHIP_CONTAINER);
	
		$storage = $commander->getPevToLoot() * Commander::COEFFLOOT;
		$storage += round($storage * ((2 * $bonus) / 100));

		$resourcesLooted = 0;
		$resourcesLooted = ($storage > $place->resources) ? $place->resources : $storage;

		$place->resources -= $resourcesLooted;
		$commander->resources = $resourcesLooted;

		LiveReport::$resources = $resourcesLooted;
	}

	private function lootAPlayerPlace($commander, $playerBonus, $placeBase) {
		$bonus = ($commander->rPlayer != $this->session->get('playerId'))
			? $playerBonus->bonus->get(PlayerBonus::SHIP_CONTAINER)
			: $this->session->get('playerBonus')->get(PlayerBonus::SHIP_CONTAINER);

		$resourcesToLoot = $placeBase->getResourcesStorage() - Commander::LIMITTOLOOT;

		$storage = $commander->getPevToLoot() * Commander::COEFFLOOT;
		$storage += round($storage * ((2 * $bonus) / 100));

		$resourcesLooted = 0;
		$resourcesLooted = ($storage > $resourcesToLoot) ? $resourcesToLoot : $storage;

		if ($resourcesLooted > 0) {
			$this->orbitalBaseManager->decreaseResources($placeBase, $resourcesLooted);
			$commander->resources = $resourcesLooted;

			LiveReport::$resources = $resourcesLooted;
		}
	}

	private function startFight(Place $place, $commander, $player, $enemyCommander = NULL, $enemyPlayer = NULL, $pvp = FALSE) {
		if ($pvp == TRUE) {
			$commander->setArmy();
			$enemyCommander->setArmy();

			$this->fightManager->startFight($commander, $player, $enemyCommander, $enemyPlayer);
		} else {
			$commander->setArmy();
			$computerCommander = $this->createVirtualCommander($place);

			$this->fightManager->startFight($commander, $player, $computerCommander);
		}
	}

	private function createReport(Place $place) {
		$report = new Report();

		$report->rPlayerAttacker = LiveReport::$rPlayerAttacker;
		$report->rPlayerDefender =  LiveReport::$rPlayerDefender;
		$report->rPlayerWinner = LiveReport::$rPlayerWinner;
		$report->avatarA = LiveReport::$avatarA;
		$report->avatarD = LiveReport::$avatarD;
		$report->nameA = LiveReport::$nameA;
		$report->nameD = LiveReport::$nameD;
		$report->levelA = LiveReport::$levelA;
		$report->levelD = LiveReport::$levelD;
		$report->experienceA = LiveReport::$experienceA;
		$report->experienceD = LiveReport::$experienceD;
		$report->palmaresA = LiveReport::$palmaresA;
		$report->palmaresD = LiveReport::$palmaresD;
		$report->resources = LiveReport::$resources;
		$report->expCom = LiveReport::$expCom;
		$report->expPlayerA = LiveReport::$expPlayerA;
		$report->expPlayerD = LiveReport::$expPlayerD;
		$report->rPlace = $place->id;
		$report->type = LiveReport::$type;
		$report->round = LiveReport::$round;
		$report->importance = LiveReport::$importance;
		$report->squadrons = LiveReport::$squadrons;
		$report->dFight = LiveReport::$dFight;
		$report->isLegal = LiveReport::$isLegal;
		$report->placeName = ($place->baseName == '') ? 'planète rebelle' : $place->baseName;
		$report->setArmies();
		$report->setPev();
		
		$this->reportManager->add($report);
		LiveReport::clear();

		return $report;
	}

	/**
	 * @param Place $place
	 * @param string $case
	 * @param Commander $commander
	 * @param Report $report
	 */
	private function sendNotif(Place $place, $case, Commander $commander, $report = NULL) {
		switch ($case) {
			case Place::CHANGESUCCESS:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Déplacement réussi');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId(), $commander->getName())
					->addTxt(' est arrivé sur ')
					->addLnk('map/base-' . $place->id, $place->baseName)
					->addTxt('.')
					->addEnd();
				$this->notificationManager->add($notif);
				break;

			case Place::CHANGEFAIL:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Déplacement réussi');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId(), $commander->getName())
					->addTxt(' s\'est posé sur ')
					->addLnk('map/base-' . $place->id, $place->baseName)
					->addTxt('. Il est en garnison car il n\'y avait pas assez de place en orbite.')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::CHANGELOST:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Déplacement raté');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId(), $commander->getName())
					->addTxt(' n\'est pas arrivé sur ')
					->addLnk('map/base-' . $place->id, $place->baseName)
					->addTxt('. Cette base ne vous appartient pas. Elle a pu être conquise entre temps.')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::LOOTEMPTYSSUCCESS:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Pillage réussi');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' a pillé la planète rebelle située aux coordonnées ')
					->addLnk('map/place-' . $place->id, Game::formatCoord($place->xSystem, $place->ySystem, $place->position, $place->rSector))
					->addTxt('.')
					->addSep()
					->addBoxResource('resource', Format::number($commander->getResourcesTransported()), 'ressources pillées')
					->addBoxResource('xp', '+ ' . Format::number($commander->earnedExperience), 'expérience de l\'officier')
					->addSep()
					->addLnk('fleet/view-archive/report-' . $report, 'voir le rapport')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::LOOTEMPTYFAIL:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Pillage raté');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/view-memorial', $commander->getName())
					->addTxt(' est tombé lors de l\'attaque de la planète rebelle située aux coordonnées ')
					->addLnk('map/place-' . $place->id, Game::formatCoord($place->xSystem, $place->ySystem, $place->position, $place->rSector))
					->addTxt('.')
					->addSep()
					->addTxt('Il a désormais rejoint le Mémorial. Que son âme traverse l\'Univers dans la paix.')
					->addSep()
					->addLnk('fleet/view-archive/report-' . $report, 'voir le rapport')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::LOOTPLAYERWHITBATTLESUCCESS:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Pillage réussi');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' a pillé la planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $place->rPlayer, $place->playerName)
					->addTxt('.')
					->addSep()
					->addBoxResource('resource', Format::number($commander->getResourcesTransported()), 'ressources pillées')
					->addBoxResource('xp', '+ ' . Format::number($commander->earnedExperience), 'expérience de l\'officier')
					->addSep()
					->addLnk('fleet/view-archive/report-' . $report, 'voir le rapport')
					->addEnd();
				$this->notificationManager->add($notif);

				$notif = new Notification();
				$notif->setRPlayer($place->rPlayer);
				$notif->setTitle('Rapport de pillage');
				$notif->addBeg()
					->addTxt('L\'officier ')
					->addStg($commander->getName())
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $commander->getRPlayer(), $commander->getPlayerName())
					->addTxt(' a pillé votre planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt('.')
					->addSep()
					->addBoxResource('resource', Format::number($commander->getResourcesTransported()), 'ressources pillées')
					->addSep()
					->addLnk('fleet/view-archive/report-' . $report, 'voir le rapport')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::LOOTPLAYERWHITBATTLEFAIL:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Pillage raté');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/view-memorial', $commander->getName())
					->addTxt(' est tombé lors du pillage de la planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $place->rPlayer, $place->playerName)
					->addTxt('.')
					->addSep()
					->addTxt('Il a désormais rejoint le Mémorial. Que son âme traverse l\'Univers dans la paix.')
					->addSep()
					->addLnk('fleet/view-archive/report-' . $report, 'voir le rapport')
					->addEnd();
				$this->notificationManager->add($notif);

				$notif = new Notification();
				$notif->setRPlayer($place->rPlayer);
				$notif->setTitle('Rapport de combat');
				$notif->addBeg()
					->addTxt('L\'officier ')
					->addStg($commander->getName())
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $commander->getRPlayer(), $commander->getPlayerName())
					->addTxt(' a attaqué votre planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt('.')
					->addSep()
					->addTxt('Vous avez repoussé l\'ennemi avec succès.')
					->addSep()
					->addLnk('fleet/view-archive/report-' . $report, 'voir le rapport')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::LOOTPLAYERWHITOUTBATTLESUCCESS:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Pillage réussi');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' a pillé la planète non défendue ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $place->rPlayer, $place->playerName)
					->addTxt('.')
					->addSep()
					->addBoxResource('resource', Format::number($commander->getResourcesTransported()), 'ressources pillées')
					->addBoxResource('xp', '+ ' . Format::number($commander->earnedExperience), 'expérience de l\'officier')
					->addEnd();
				$this->notificationManager->add($notif);

				$notif = new Notification();
				$notif->setRPlayer($place->rPlayer);
				$notif->setTitle('Rapport de pillage');
				$notif->addBeg()
					->addTxt('L\'officier ')
					->addStg($commander->getName())
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $commander->getRPlayer(), $commander->getPlayerName())
					->addTxt(' a pillé votre planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt('. Aucune flotte n\'était en position pour la défendre. ')
					->addSep()
					->addBoxResource('resource', Format::number($commander->getResourcesTransported()), 'ressources pillées')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::LOOTLOST:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Erreur de coordonnées');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' n\'a pas attaqué la planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt(' car son joueur est de votre faction, sous la protection débutant ou un allié.')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::CONQUEREMPTYSSUCCESS:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Colonisation réussie');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' a colonisé la planète rebelle située aux coordonnées ')  
					->addLnk('map/place-' . $place->id , Game::formatCoord($place->xSystem, $place->ySystem, $place->position, $place->rSector) . '.')
					->addBoxResource('xp', '+ ' . Format::number($commander->earnedExperience), 'expérience de l\'officier')
					->addTxt('Votre empire s\'étend, administrez votre ')
					->addLnk('bases/base-' . $place->id, 'nouvelle planète')
					->addTxt('.')
					->addSep()
					->addLnk('fleet/view-archive/report-' . $report, 'voir le rapport')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::CONQUEREMPTYFAIL:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Colonisation ratée');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/view-memorial', $commander->getName())
					->addTxt(' est tombé lors de l\'attaque de la planète rebelle située aux coordonnées ')
					->addLnk('map/place-' . $place->id, Game::formatCoord($place->xSystem, $place->ySystem, $place->position, $place->rSector))
					->addTxt('.')
					->addSep()
					->addTxt('Il a désormais rejoint le Mémorial. Que son âme traverse l\'Univers dans la paix.')
					->addSep()
					->addLnk('fleet/view-archive/report-' . $report, 'voir le rapport')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::CONQUERPLAYERWHITOUTBATTLESUCCESS:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Conquête réussie');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' a conquis la planète non défendue ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $place->rPlayer, $place->playerName)
					->addTxt('.')
					->addSep()
					->addBoxResource('xp', '+ ' . Format::number($commander->earnedExperience), 'expérience de l\'officier')
					->addTxt('Elle est désormais votre, vous pouvez l\'administrer ')
					->addLnk('bases/base-' . $place->id, 'ici')
					->addTxt('.')
					->addEnd();
				$this->notificationManager->add($notif);

				$notif = new Notification();
				$notif->setRPlayer($place->rPlayer);
				$notif->setTitle('Planète conquise');
				$notif->addBeg()
					->addTxt('L\'officier ')
					->addStg($commander->getName())
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $commander->getRPlayer(), $commander->getPlayerName())
					->addTxt(' a conquis votre planète non défendue ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt('.')
					->addSep()
					->addTxt('Impliquez votre faction dans une action punitive envers votre assaillant.')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::CONQUERLOST:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Erreur de coordonnées');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' n\'a pas attaqué la planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt(' car le joueur est dans votre faction, sous la protection débutant ou votre allié.')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::COMEBACK:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Rapport de retour');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' est de retour sur votre base ')
					->addLnk('map/place-' . $commander->getRBase(), $commander->getBaseName())
					->addTxt(' et rapporte ')
					->addStg(Format::number($commander->getResourcesTransported()))
					->addTxt(' ressources à vos entrepôts.')
					->addEnd();
				$this->notificationManager->add($notif);
				break;
			
			default: break;
		}
	}

	/**
	 * @param Place $place
	 * @param string $case
	 * @param Commander $commander
	 * @param array $reports
	 */
	private function sendNotifForConquest(Place $place, $case, $commander, $reports = array()) {
		$nbrBattle = count($reports);
		switch($case) {
			case Place::CONQUERPLAYERWHITBATTLESUCCESS:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Conquête réussie');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/commander-' . $commander->getId() . '/sftr-3', $commander->getName())
					->addTxt(' a conquis la planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $place->rPlayer, $place->playerName)
					->addTxt('.')
					->addSep()
					->addTxt($nbrBattle . Format::addPlural($nbrBattle, ' combats ont eu lieu.', ' seul combat a eu lieu'))
					->addSep()
					->addBoxResource('xp', '+ ' . Format::number($commander->earnedExperience), 'expérience de l\'officier')
					->addSep()
					->addTxt('Elle est désormais vôtre, vous pouvez l\'administrer ')
					->addLnk('bases/base-' . $place->id, 'ici')
					->addTxt('.');
				for ($i = 0; $i < $nbrBattle; $i++) {
					$notif->addSep();
					$notif->addLnk('fleet/view-archive/report-' . $reports[$i], 'voir le ' . Format::ordinalNumber($i + 1) . ' rapport');
				}
				$notif->addEnd();
				$this->notificationManager->add($notif);

				$notif = new Notification();
				$notif->setRPlayer($place->rPlayer);
				$notif->setTitle('Planète conquise');
				$notif->addBeg()
					->addTxt('L\'officier ')
					->addStg($commander->getName())
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $commander->getRPlayer(), $commander->getPlayerName())
					->addTxt(' a conquis votre planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt('.')
					->addSep()
					->addTxt($nbrBattle . Format::addPlural($nbrBattle, ' combats ont eu lieu.', ' seul combat a eu lieu'))
					->addSep()
					->addTxt('Impliquez votre faction dans une action punitive envers votre assaillant.');
				for ($i = 0; $i < $nbrBattle; $i++) {
					$notif->addSep();
					$notif->addLnk('fleet/view-archive/report-' . $reports[$i], 'voir le ' . Format::ordinalNumber($i + 1) . ' rapport');
				}
				$notif->addEnd();
				$this->notificationManager->add($notif);
				break;
			case Place::CONQUERPLAYERWHITBATTLEFAIL:
				$notif = new Notification();
				$notif->setRPlayer($commander->getRPlayer());
				$notif->setTitle('Conquête ratée');
				$notif->addBeg()
					->addTxt('Votre officier ')
					->addLnk('fleet/view-memorial/', $commander->getName())
					->addTxt(' est tombé lors de la tentive de conquête de la planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $place->rPlayer, $place->playerName)
					->addTxt('.')
					->addSep()
					->addTxt($nbrBattle . Format::addPlural($nbrBattle, ' combats ont eu lieu.', ' seul combat a eu lieu'))
					->addSep()
					->addTxt('Il a désormais rejoint de Mémorial. Que son âme traverse l\'Univers dans la paix.');
				for ($i = 0; $i < $nbrBattle; $i++) {
					$notif->addSep();
					$notif->addLnk('fleet/view-archive/report-' . $reports[$i], 'voir le ' . Format::ordinalNumber($i + 1) . ' rapport');
				}
				$notif->addEnd();
				$this->notificationManager->add($notif);

				$notif = new Notification();
				$notif->setRPlayer($place->rPlayer);
				$notif->setTitle('Rapport de combat');
				$notif->addBeg()
					->addTxt('L\'officier ')
					->addStg($commander->getName())
					->addTxt(' appartenant au joueur ')
					->addLnk('embassy/player-' . $commander->getRPlayer(), $commander->getPlayerName())
					->addTxt(' a tenté de conquérir votre planète ')
					->addLnk('map/place-' . $place->id, $place->baseName)
					->addTxt('.')
					->addSep()
					->addTxt($nbrBattle . Format::addPlural($nbrBattle, ' combats ont eu lieu.', ' seul combat a eu lieu'))
					->addSep()
					->addTxt('Vous avez repoussé l\'ennemi avec succès. Bravo !');
				for ($i = 0; $i < $nbrBattle; $i++) {
					$notif->addSep();
					$notif->addLnk('fleet/view-archive/report-' . $reports[$i], 'voir le ' . Format::ordinalNumber($i + 1) . ' rapport');
				}
				$notif->addEnd();
				$this->notificationManager->add($notif);
				break;

			default: break;
		}
	}

	/**
	 * @param Place $place
	 * @return Commander
	 */
	public function createVirtualCommander(Place $place) {
		$population = $place->population;
		$vCommander = new Commander();
		$vCommander->id = 'Null';
		$vCommander->rPlayer = ID_GAIA;
		$vCommander->name = 'rebelle';
		$vCommander->avatar = 't3-c4';
		$vCommander->sexe = 1;
		$vCommander->age = 42;
		$vCommander->statement = 1;
		$vCommander->level = ceil((((($place->maxDanger / (Place::DANGERMAX / Place::LEVELMAXVCOMMANDER))) * 9) + ($place->population / (Place::POPMAX / Place::LEVELMAXVCOMMANDER))) / 10);

		$nbrsquadron = ceil($vCommander->level * (($place->danger + 1) / ($place->maxDanger + 1)));

		$army = array();
		$squadronsIds = array();

		for ($i = 0; $i < $nbrsquadron; $i++) {
			$aleaNbr = ($place->coefHistory * $place->coefResources * $place->position * $i) % SquadronResource::size();
			$army[] = SquadronResource::get($vCommander->level, $aleaNbr);
			$squadronsIds[] = 0;
		}

		for ($i = $vCommander->level - 1; $i >= $nbrsquadron; $i--) {
			$army[$i] = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, Utils::now());
			$squadronsIds[] = 0;
		}

		$vCommander->setSquadronsIds($squadronsIds);
		$vCommander->setArmyInBegin($army);
		$vCommander->setArmy();
		$vCommander->setPevInBegin();

		return $vCommander;
	}
}