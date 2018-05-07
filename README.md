# registerQuick :replace default register system by a quickest way.. #

If survey have token and register is activated, allow admin user to enable new registered participants to be redirect to the survey with the new token after registering.

## Installation

This plugin was tested on LimeSurvey 3.7.1 and can work on all version after 3.0.0.

This plugin **need** [twigExtendByPlugins](https://gitlab.com/SondagesPro/registerQuick) to work.

### Via GIT
- Go to your LimeSurvey Directory
- Clone in plugins/registerQuick directory : `git clone https://gitlab.com/SondagesPro/registerQuick.git registerQuick`

### Via ZIP dowload
- Get the file [registerQuick.zip](http://extensions.sondages.pro/IMG/auto/registerQuick.zip)
- Extract : `unzip registerQuick.zip`
- Move the directory to plugins/ directory inside LimeSurvey

### Activate

You just need to activate plugin like other plugin, see [Install and activate a plugin for LimeSurvey](https://extensions.sondages.pro/install-and-activate-a-plugin-for-limesurvey.html).

## Usage
- Edit plugin settings via Survey settings, plugin tab. Set _Use quick registering_ to on to use the plugin.
- _Email settings_: choose if you need, allow or disallow email
- _Existing Email_: choose what to do if email adress have already a token. You can create a new token; reload response except if survey is complete and update responses with one token is disallowed; always reload response.
- _Privacy of response_: If email exist : disable reloading survey without token. Reloading existing response can have security issue, then you can force entering token to reload response.
- _Send the email_: send or not the core register email. In case of privacy of response : register email is always sent.
- _Show the token form_: you can show the token form just after the registering form (currently disable).

## Contribute

Issue and pull request are welcome on [gitlab](https://gitlab.com/SondagesPro/registerQuick).

Translation are managed on <https://translate.sondages.pro>, you must register before update string.
If you language is not available, open a issue on [gitlab](https://gitlab.com/SondagesPro/registerQuick).

## Home page & Copyright
- HomePage <http://extensions.sondages.pro/>
- Copyright © 2017-2018 Denis Chenu <http://sondages.pro>
- Copyright © 2017 SICODA GmbH <http://www.sicoda.de>
- Copyright © 2017 MarketAccess Communications <https://www.marketaccess.ca/>
- Licence : GNU Affero General Public License <https://www.gnu.org/licenses/agpl-3.0.html>
