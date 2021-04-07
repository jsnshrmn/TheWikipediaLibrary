<?php
use MediaWiki\MediaWikiServices;
use TheWikipediaLibrary\PreferenceHelper;

/**
 * TheWikipediaLibrary extension hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */
class TheWikipediaLibraryHooks {

	/**
	 * Add The Wikipedia Library - eligibility events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notifications['twl-eligible'] = [
			EchoAttributeManager::ATTR_LOCATORS => [
				'EchoUserLocator::locateEventAgent'
			],
			'canNotifyAgent' => true,
			'category' => 'system',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => 'TwlEligiblePresentationModel'
		];

		$icons['twl-eligible'] = [
			'path' => 'TheWikipediaLibrary/modules/icons/twl-eligible.svg'
		];

		return true;
	}

	/**
	 * Add API preference tracking whether the user has been notified already.
	 * @param User $user
	 * @param array &$preferences
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['twl-notified'] = [
			'type' => 'api'
		];
	}

	/**
	 * Send a Wikipedia Library notification if the user has reached 6 months and 500 edits.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 *
	 * @note parameters include classes not available before 1.35, so for those typehints
	 * are not used. The variable name reflects the class
	 *
	 * @param WikiPage $wikiPage
	 * @param mixed $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param mixed $revisionRecord
	 * @param mixed $editResult
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		$userIdentity,
		string $summary,
		int $flags,
		$revisionRecord,
		$editResult
	) {
		global $wgTwlSendNotifications;
		if ( $wgTwlSendNotifications ) {
			$user = User::newFromIdentity( $userIdentity );
			self::maybeSendNotification( $user );
		}
	}

	/**
	 * Determine Twl Eligibility
	 *
	 * @param mixed $centralAuthUser
	 *
	 * @note CentralAuthUser class mock in tests doesn't work with typehints,
	 * so that typehint is not used. The variable name reflects the class.
	 */
	public static function isTwlEligible( $centralAuthUser ) {
		global $wgTwlEditCount, $wgTwlRegistrationDays;

		// Check eligibility
		$accountAge = (int)wfTimestamp( TS_UNIX ) -
			(int)wfTimestamp( TS_UNIX, $centralAuthUser->getRegistration() );
		$minimumAge = $wgTwlRegistrationDays * 24 * 3600;

		if ( $centralAuthUser->getGlobalEditCount() >= $wgTwlEditCount && $accountAge >= $minimumAge ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Shared implementation for PageContentSaveComplete and PageSaveComplete
	 *
	 * @param User $user
	 */
	private static function maybeSendNotification( User $user ) {
		// Send a notification if the user has at least $wgTwlEditCount edits and their account
		// is at least $wgTwlRegistrationDays days old
		DeferredUpdates::addCallableUpdate( function () use ( $user ) {
			// Only proceed if we're dealing with an SUL account
			$globalUser = CentralAuthUser::getInstance( $user );
			if ( !$globalUser->isAttached() ) {
				return;
			}

			// Only proceed if we haven't already notified this user
			$twlNotified = PreferenceHelper::getGlobalPreference( $user, 'twl-notified' );
			if ( $twlNotified === 'yes' ) {
				return;
			// Set the twl-notified preference to false if we haven't notified this user
			} else if ($twlNotified === null ) {
				PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'no' );
				$twlNotified = PreferenceHelper::getGlobalPreference( $user, 'twl-notified' );
			}

			// Notify the user if they are eligible and haven't been notified yet
			if ( $twlNotified === 'no' && self::isTwlEligible( $globalUser ) ) {
				EchoEvent::create( [
					'type' => 'twl-eligible',
					'agent' => $user,
				] );

				// Set the twl-notified preference globally, so we'll know not to notify this user again
				PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'yes' );
			}
		} );
	}
}
