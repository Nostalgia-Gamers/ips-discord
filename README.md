### Support for 4.3.*

The original developer of this plugin abandoned the project so I have create a new repository for this project and made modifications to support 4.3.*. They may not be perfect, but they seem to work.

* I am open to contributions and will review pull-requests

### Improved Rate Limiting

I have added support for better handling of Discords rate limits, this helps syncing with larger discords.

### Information

* This is a Discord Integration for IPS 4.3.x.
* Tested on 4.3.3, might work on older versions

### Installation

* Create a directory called 'discord' in applications, then place these files into that new directory then install as normal. ie; {IPSPATH}/applications/discord/{these files}

### Current features

* Group/Role syncing.
* OAUTH2 authentication for IPS.
* Ban syncing.
* Post automated notifications to channels about new topics/posts.

### Known Issues

- Disassociating discord doesn't appear to work currently
- No settings for enabling/disable profile picture sync

Open to pull requests for these issues.
