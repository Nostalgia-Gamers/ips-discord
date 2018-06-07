<?php

namespace IPS\Login;

/**
 * Class Discord
 *
 * @package \IPS\discord
 */
class _Discord extends LoginAbstract
{
    /**
     * @brief Icon
     * @var string $icon
     * @TODO
     */
    public static $icon = 'lock';

    	/**
	 * @brief    User data
	 */
	protected $user = NULL;

    /**
     * Get Form
     *
     * @param	\IPS\Http\Url	$url			The URL for the login page
     * @param	bool			$ucp			Is UCP? (as opposed to login form)
     * @param	\IPS\Http\Url	$destination	The URL to redirect to after a successful login
     *
     * @return	string
     */
    public function loginForm( \IPS\Http\Url $url, $ucp = FALSE, \IPS\Http\Url $destination = NULL )
    {
        return \IPS\Theme::i()
            ->getTemplate( 'login', 'discord', 'global' )
            ->discord(
                (string) $this->_discordSignInUrl(
                    ( $ucp ? 'ucp' : \IPS\Dispatcher::i()->controllerLocation ),
                    $destination
                )
            );
    }

    
    /**
     * Get API data
     *
     * @return array
     * @throws \Exception
     */
    protected function get()
    {
        $discordMember = new \IPS\discord\Api\Member;
        $userData = $discordMember->getDiscordUser( $this->authToken );

        if ( !$userData['verified'] )
        {
            throw new \IPS\discord\Api\Exception\NotVerifiedException();
        }

        return $userData;
    }

    /**
     * Authenticate
     *
     * @param	string			$url	The URL for the login page
     * @param	\IPS\Member		$member	If we want to integrate this login method with an existing member, provide the member object
     * @return	\IPS\Member
     * @throws	\IPS\Login\Exception
     */
    public function authenticate( $url, $member = NULL )
    {
        try
        {
            if ( \IPS\Request::i()->state !== \IPS\Session::i()->csrfKey )
            {
                throw new \IPS\Login\Exception( 'CSRF_FAIL', \IPS\Login\Exception::INTERNAL_ERROR );
            }

            /* Retrieve access token */
            $response = \IPS\Http\Url::external( \IPS\discord\Api::OAUTH2_URL . 'token' )
                ->request()
                ->post([
                    'client_id' => \IPS\Settings::i()->discord_client_id,
                    'client_secret' => \IPS\Settings::i()->discord_client_secret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri'	=> ((string) \IPS\Http\Url::internal( 'applications/discord/interface/oauth/auth.php', 'none' )),
                    'code' => \IPS\Request::i()->code
                ])
                ->decodeJson();

            if ( isset( $response['error'] ) || !isset( $response['access_token'] ) )
            {
                throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
            }

            /* Get user data */
            $discordMember = new \IPS\discord\Api\Member;
            $userData = $discordMember->getDiscordUser( $response['access_token'] );

            if ( !$userData['verified'] )
            {
                \IPS\Output::i()->error( 'discord_not_verified', '' );
            }

            /* Set member properties */
            $memberProperties = [
                'discord_id' => $userData['id'],
                'discord_token' => $response['access_token']
            ];

            if ( isset( $response['refresh_token'] ) )
            {
                $memberProperties['discord_token'] = $response['refresh_token'];
            }

            $loggedInUser = \IPS\Member::loggedIn();

			if ($loggedInUser->member_id === NULL)
			{
                /* Find or create member */
                $member = $this->createOrUpdateAccount(
                    $member ?: \IPS\Member::load( $userData['id'], 'discord_id' ),
                    $memberProperties,
                    $this->settings['real_name'] ? $userData['username'] : NULL,
                    $userData['email'],
                    $response['access_token'],
                    array(
                        'photo' => TRUE,
                    )
                );
			} 
			else 
			{
                $member = $loggedInUser;

                $discordMember = new \IPS\discord\Api\Member;
                $member->discord_id = $userData['id'];
                $member->discord_token = $response['access_token'];

                if ( isset( $response['refresh_token'] ) ) $member->discord_token = $response['refresh_token'];

                $member->save();
            }

            /* Sync user */
            $guildMember = new \IPS\discord\Api\GuildMember;
            $guildMember->update( $member );

            /* Return */
            return $member;
        }
        catch ( \IPS\Http\Request\Exception $e )
        {
            throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
        }
    }

    /**
     * Link Account
     *
     * @param	\IPS\Member	$member		The member
     * @param	mixed		$details	Details as they were passed to the exception thrown in authenticate()
     * @return	void
     */
    public static function link( \IPS\Member $member, $details )
    {
        /* Get user data */
        $discordMember = new \IPS\discord\Api\Member;
        $userData = $discordMember->getDiscordUser( $details );
        $member->discord_id = $userData['id'];
        $member->discord_token = $details;
        $member->save();

        /* Sync member */
        $guildMember = new \IPS\discord\Api\GuildMember;
        $guildMember->update( $member );
    }

    /**
     * ACP Settings Form
     *
     * @return	array	List of settings to save - settings will be stored to core_login_handlers.login_settings DB field
     * @code
    return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
     * @endcode
     */
    public function acpForm()
    {
        /* No config is needed here, all information is retrieved from the application settings. */
        return [];
    }

    /**
	 * Show in Account Settings
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for if it should show generally
	 * @return	bool
	 */
	public function showInUcp( \IPS\Member $member = NULL )
	{		
	    $member = \IPS\Member::loggedIn();
	    
		return $member;
    }
    
    /**
     * Logo for account settings, waiting for IPB to upgrade FontAwesome version - then we can use 'Discord' icon
     */
    public function logoForUcp()
	{
		return 'gamepad';
    }
    
    /**
     * Get user data
     *
     * @return	array
     */
    protected function user(\IPS\Member $member = NULL)
    {
        if ( $member != NULL ) $this->member = $member;

        if ( $this->user === NULL && $this->member->discord_token )
        {
            try
            {
                $response = \IPS\Http\Url::external( \IPS\discord\Api::OAUTH2_URL . 'token' )->request()->post( [
                    'client_id'		=> \IPS\Settings::i()->discord_client_id,
                    'client_secret'	=> \IPS\Settings::i()->discord_client_secret,
                    'refresh_token'	=> $this->member->discord_token,
                    'grant_type'	=> 'refresh_token'
                ] )->decodeJson();

                if ( isset( $response['access_token'] ) )
                {
                    $this->authToken = $response['access_token'];
                    $this->user = $this->get();
                }

                /* Sync roles */
                $guildMember = new \IPS\discord\Api\GuildMember;
                $guildMember->update( $this->member );
            }
            catch ( \IPS\Http\Request\Exception $e )
            {
                $this->member->discord_token = NULL;
                $this->member->save();

                \IPS\Log::log( $e, 'discord' );
            }
            catch ( \IPS\discord\Api\Exception\NotVerifiedException $e )
            {
                $this->member->discord_token = NULL;
                $this->member->save();

                \IPS\Log::log( $e, 'discord' );

                \IPS\Output::i()->error( 'discord_not_verified', '' );
            }
        }

        return $this->user;
    }

    /**
	 * Get user's profile name
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	string|NULL
	 */
	public function userProfileName( \IPS\Member $member )
	{
        $user = $this->user( $member );

        if ( isset( $user['username'] ) )
        {
            return $user['username'];
        }

        return NULL;
	}

    /**
	 * Get user's profile photo
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	\IPS\Http\Url|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userProfilePhoto( \IPS\Member $member )
	{
        try
        {
            $user = $this->user( $member );
    
            if ( isset( $user['avatar'] ) && !empty( $user['avatar'] ) )
            {
                return \IPS\Http\Url::external( \IPS\discord\Api::API_URL . "users/{$user['id']}/avatars/{$user['avatar']}.jpg" );
            }
        }
        catch ( \IPS\Http\Request\Exception $e )
        {
            \IPS\Log::log( $e, 'discord' );
        }
        return NULL;
	}


    /**
     * Test Settings
     *
     * @return	bool
     * @throws	\IPS\Http\Request\Exception
     * @throws	\UnexpectedValueException	If response code is not 302
     */
    public function testSettings()
    {
        return TRUE;
    }

    /**
     * Can a member sign in with this login handler?
     * Used to ensure when a user disassociates a social login that they have some other way of logging in
     *
     * @param	\IPS\Member	$member	The member
     * @return	bool
     */
    public function canProcess( \IPS\Member $member )
    {
        return ( $member->discord_id && $member->discord_token );
    }

    /**
     * Get sign in URL
     *
     * @param	string			$base			Controls where the user is taken back to
     * @param	\IPS\Http\Url	$destination	The URL to redirect to after a successful login
     *
     * @return	\IPS\Http\Url
     */
    protected function _discordSignInUrl( $base, \IPS\Http\Url $destination = NULL )
    {
        $params = [
            'response_type'	=> 'code',
            'client_id' => \IPS\Settings::i()->discord_client_id,
            'redirect_uri'	=> ( (string) \IPS\Http\Url::internal( 'applications/discord/interface/oauth/auth.php', 'none' ) ),
            'scope' => ( \IPS\discord\Api::SCOPE_EMAIL . ' ' . \IPS\discord\Api::SCOPE_IDENTIFY ),
            'state' => ( $base . '-' . \IPS\Session::i()->csrfKey . '-' . ( $destination ? base64_encode( $destination ) : '' ) )
        ];

        return \IPS\Http\Url::external( \IPS\discord\Api::OAUTH2_URL . 'authorize' )->setQueryString( $params );
    }

    /**
     * Can a member change their email/password with this login handler?
     *
     * @param	string		$type	'email' or 'password'
     * @param	\IPS\Member	$member	The member
     * @return	bool
     */
    public function canChange( $type, \IPS\Member $member )
    {
        return FALSE;
    }
}
