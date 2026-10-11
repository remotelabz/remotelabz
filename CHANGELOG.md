Unreleased (2.5.0-dev)
----------------------

### Added
- Shared labs (#1128): a lab can now be shared with other labs of a same group through explicit rules `(source lab, target lab, group)`, stored in the new `lab_share` table. The rules are read and written with the new `shares` key of the lab API and their topology is broadcast to the workers as one message per group (`SecurityMessage`). Only the target lab has to belong to the group, so a lab can be shared with a group while staying hidden from its members. The topology covers both the instances launched by the group itself and the sandbox instances launched by its members.
- Hidden devices (#1467): a device can now be marked as hidden through the new `hidden` boolean of the device API (`device` table). A hidden device is only visible to the lab author and to the system administrators: it is filtered out of the lab payload (`api_get_lab`) and of the lab instance payload (`api_get_lab_instance`) for every other user, from the network topology, from the device count of the lab page and from the editor of any user who is neither the author nor an admin (who, in turn, see an eye icon on the node and a toggle in the device form). Hidden devices are always started with the lab even when the option to start the devices is disabled, and they never block the "Leave lab" button for the users who cannot see them. They are stopped and deleted along with the lab instance like any other device, and the worker, sandbox and export serializations are never filtered so an exported lab keeps its hidden devices. Lab imports and lab copies preserve the flag for their new author. The state notifications of a hidden device instance are only sent to the lab author, and the instance API denies view, start, stop, reset and logs of a hidden device instance to every user but its lab author and the admins.

2.1.0-beta
----------

### Added
- Groups can now join labs with instances
- Labs and activities are now fusionned into **Labs**. Activity description is now Lab description.

### Updated
- Device instances controls are now asynchronous.
- Most of the previous objects are now included into others to create **templates**. Templates can be used to be cloned to create new objects.
- Network interfaces are now managed by lab.


2.0.0-rc3:
----------

### Added
- Courses and groups are now fusionned into **Groups**. You can now create groups which may contains subgroups and activities
- Dark theme 🌓

### Updated
- Public and admin areas are now seperated
- Groups and activities are now linked. An activity belongs to a group, and an user must be part of the owner group or one of its subgroups to see/launch an activity (depending on the group's privacy settings)
- You are automatically disconnected when your JWT token is expired and you must login again in order to refresh it. When you logged in again succesfully, you are redirected to the last page you visited
- Various visual updates

2.0.0-rc2:
----------

### Features
- New installer : set your environment directly from CLI installer and handle properly installation workflow.
- A device may now be used in different labs by the same user.
- Labs may now be connected to the internet.

### Bug fixes
- Fixed an issue preventing to start multiple labs with the same device (#417)
- Fixed an issue preventing device instances to be mapped.

### Updates
- Data fixtures has been updated with more realistic data.