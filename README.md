## Discord Integration For Invision Power Board

* This is a Discord Integration plugin for IPS 4.3.x.
* Tested on 4.3.3, might work on older versions

### Features

* Group/Role syncing
* OAUTH2 authentication (Login with Discord)
* Ban syncing
* Post automated notifications to channels about new topics/posts

### Support for 4.3.*

The original developer of this plugin abandoned the project so I have created a new repository for this project and made modifications to support 4.3.*. They may not be perfect, but they seem to work.

### Improved Rate Limiting

I have added support for better handling of Discords rate limits, this helps syncing with larger discords.

### Installation

* Create a directory called 'discord' in applications, then place these files into that new directory then install as normal. ie; {IPSPATH}/applications/discord/{these files}

### Contributing 

I am open to contributions and will review pull-requests

### Known Issues

- Disassociating discord doesn't appear to work currently
- No settings for enabling/disable profile picture sync

Open to pull requests for these issues.
