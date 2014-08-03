<?/*
Imo Request Access Key from Twitter
Copyright(C) 2010/04/07-2010/04/07 Imajo Kentaro (imos at imoz.jp).

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once('oauth.php');
require_once("../config_en.php");

// OAuth settings
define('OAUTH_CONSUMER_KEY', $contoken);
define('OAUTH_CONSUMER_SECRET', $consecret);

// Get request key
$key = oauth_request('http://twitter.com/oauth/request_token');
parse_str($key, $token);

echo "Press Enter Key after following URL:\n";
echo "http://twitter.com/oauth/authorize?oauth_token=$token[oauth_token]\n";
fgets(STDIN);

$key = oauth_request("http://twitter.com/oauth/access_token?$key");
parse_str($key, $token);

echo "User ID/Name: $token[user_id] / $token[screen_name]\n";
echo "Access Key: $token[oauth_token]\n";
echo "Access Secret: $token[oauth_token_secret]\n";
