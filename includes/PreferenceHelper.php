<?php
/**
 * User preference helper methods adapted from ContentTranslation extension.
 *
 * @copyright See https://raw.githubusercontent.com/wikimedia/mediawiki-extensions-ContentTranslation/736585619e98883f0907e7eb208a06d456f04c77/AUTHORS.txt
 * @license GPL-2.0-or-later
 */
namespace TheWikipediaLibrary;

use ExtensionRegistry;
use GlobalPreferences\GlobalPreferencesFactory;
use GlobalPreferences\Storage;
use MediaWiki\MediaWikiServices;
use RequestContext;
use User;

class PreferenceHelper {

	/**
	 * @param User $user
	 *
	 * @return bool
	 */
	public static function isBetaFeatureEnabled( User $user ) {
		return ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' )
			&& BetaFeatures::isFeatureEnabled( $user, 'twl' );
	}

	/**
	 * Set a global preference for the user.
	 * @param User $user
	 * @param string $preference
	 * @param string $value
	 */
	public static function setGlobalPreference( User $user, $preference, $value ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'GlobalPreferences' ) ) {
			// Need GlobalPreferences extension.
			wfLogWarning( __METHOD__ . ': Need GlobalPreferences extension. Not setting preference.' );
			return;
		}
		/** @var GlobalPreferencesFactory $globalPref */
		$globalPref = MediaWikiServices::getInstance()->getPreferencesFactory();
		'@phan-var GlobalPreferencesFactory $globalPref';
		$prefs = $globalPref->getGlobalPreferencesValues( $user, Storage::SKIP_CACHE );
		$prefs[$preference] = $value;
		$user = $user->getInstanceForUpdate();
		$globalPref->setGlobalPreferences( $user, $prefs, RequestContext::getMain() );
	}

	/**
	 * Get a global preference for the user.
	 * @param User $user
	 * @param string $preference
	 * @return string|null Preference value
	 */
	public static function getGlobalPreference( $user, $preference ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'GlobalPreferences' ) ) {
			// Need GlobalPreferences extension.
			wfLogWarning( __METHOD__ . ': Need GlobalPreferences extension. Not getting preference.' );
			return null;
		}
		/** @var GlobalPreferencesFactory $globalPref */
		$globalPref = MediaWikiServices::getInstance()->getPreferencesFactory();
		'@phan-var GlobalPreferencesFactory $globalPref';
		$prefs = $globalPref->getGlobalPreferencesValues( $user, Storage::SKIP_CACHE );
		return $prefs[$preference] ?? null;
	}
}
