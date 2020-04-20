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