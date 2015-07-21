<?php namespace Watson\Autologin;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Config\Repository;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Str;
use Watson\Autologin\Interfaces\AutologinInterface;

class Autologin
{
    /**
     * Illuminate generator instance.
     *
     * @var \Illuminate\Routing\UrlGenerator
     */
    protected $generator;

    /**
     * AutologinInterface provider instance.
     *
     * @var \Watson\Autologin\AutologinInterface
     */
    protected $provider;

    /**
     * Create a new Autologin instance.
     *
     * @param  \Illuminate\Routing\UrlGenerator
     * @return void
     */
    public function __construct(UrlGenerator $generator, AutologinInterface $provider)
    {
        $this->generator = $generator;
        $this->provider = $provider;
    }

    /**
     * Generate a link that will automatically login a user without a path
     * which will instead go to the default route definted in the config
     * file.
     *
     * @param  Authenticatable
     * @return string
     */
    public function user(Authenticatable $user)
    {
        return $this->getAutologinLink($user);
    }

    /**
     * Generate a link that will automatically login a user and then redirect
     * them to a hard-coded path as generated by the URL
     * generator.
     *
     * @param  Authenticatable  $user
     * @param  string  $path
     * @param  mixed  $extra
     * @param  bool  $secure
     * @return string
     */
    public function to(Authenticatable $user, $path, $extra = array(), $secure = null)
    {
        $path = $this->generator->to($path, $extra, $secure);

        return $this->getAutologinLink($user, $path);
    }

    /**
     * Generate a link that will automatically login a user and then redirect
     * them to a named route as generated by the URL generator.
     *
     * @param  Authenticatable  $user
     * @param  string  $name
     * @param  mixed  $paramesters
     * @param  bool  $absolute
     * @param  \Illuminate\Routing\Route  $route
     * @return string
     */
    public function route(Authenticatable $user, $name, $parameters = array(), $absolute = true, $route = null)
    {
        $path = $this->generator->route($name, $parameters, $absolute, $route);

        return $this->getAutologinLink($user, $path);
    }

    /**
     * Validate a token from storage and return the row.
     *
     * @param  string  $token
     * @return mixed
     */
    public function validate($token)
    {
        $autologin = $this->provider->findByToken($token);

        if ($autologin) {
            if (config('autologin.count')) {
                $autologin->incrementCount();
            }

            return $autologin;
        }

        return null;
    }

    /**
     * Get the link that can be used to automatically login a user to the
     * application.
     *
     * @param  Authenticatable  $user
     * @param  string  $path
     * @return string
     */
    protected function getAutologinLink(Authenticatable $user, $path = null)
    {
        // If we are supposed to remove expired tokens, let's do it now.
        if (config('autologin.remove_expired')) {
            $this->deleteExpiredTokens();
        }
        
        // Get the user ID to be associated with a token.
        $userId = $user->getAuthIdentifier();

        // Generate a random unique token that can be used for the link.
        $token = $this->getAutologinToken();

        // Save the token to storage.
        $this->provider->create([
            'user_id'    => $userId,
            'token'      => $token,
            'path'       => $path
        ]);

        // Return a link using the route from the configuration file and
        // the generated token.
        $routeName = config('autologin.route_name');
        
        return $this->generator->route($routeName, $token);
    }

    /**
     * Generate a random unique token using the length that is provided
     * in the configuration file.
     *
     * @return string
     */
    protected function getAutologinToken()
    {
        $length = config('autologin.length');

        do {
            $token = Str::random($length);
        } while ($this->provider->findByToken($token));

        return $token;
    }

    /**
     * Remove tokens that have expired from storage.
     *
     * @return bool
     */
    protected function deleteExpiredTokens()
    {
        $lifetime = config('autologin.lifetime');

        $expiry = Carbon::now()->subMinutes($lifetime);

        return $this->provider->deleteExpiredTokens($expiry);
    }
}
