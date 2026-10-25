Unreleased (2.5.0-dev)
----------------------

### Added
- Internet access per lab: a new `isInternetAuthorized` flag on the lab (`lab.is_internet_authorized`, default `true`) grants the permission to reach the Internet and is written by the lab form only (the "Enable Internet access" checkbox of the lab editor: enabling it connects the existing instances, disabling it disconnects them). The "Connect Internet"/"Disconnect Internet" button of the lab page and the matching entry of the editor "More actions" menu are visible only when the lab is internet authorized — an unauthorized lab shows no Internet control at all — and their label follows the lab-wide connected state (`LabInstance.isInternetConnected`, aggregated over the live instances of the lab). Those controls fan out an `InstanceActionMessage` (`ACTION_CONNECT` / `ACTION_DISCONNECT`) routed to the worker of every placed instance through the new `LabInternetManager` service, replacing the dead HTTP calls to `/lab/connect/internet` (a route that never existed on the workers), and never modify the permission: a lab that is authorized but manually disconnected reconnects itself at the next launch. Instances not placed yet or being deleted are skipped and follow the flag at launch instead, where `LabLaunchRequestMessageHandler` now sends `ACTION_CONNECT` right after `ACTION_CREATE` when the lab is authorized (and clears the connected state when it is not). The worker-side changes required by these two actions (missing `ACTION_DISCONNECT` case, broken state feedback, idempotence of `connectToInternet()` and the multi-lab limitation) are documented in `README-InternetConnexion.md`.
- Shared labs (#1128): a lab can now be shared with other labs of a same group through explicit rules `(source lab, target lab, group)`, stored in the new `lab_share` table. The rules are read and written with the new `shares` key of the lab API and their topology is broadcast to the workers as one message per group (`SecurityMessage`). Only the target lab has to belong to the group, so a lab can be shared with a group while staying hidden from its members. The topology covers both the instances launched by the group itself and the sandbox instances launched by its members.
- Hidden devices (#1467): a device can now be marked as hidden through the new `hidden` boolean of the device API (`device` table). A hidden device is only visible to the lab author and to the system administrators: it is filtered out of the lab payload (`api_get_lab`) and of the lab instance payload (`api_get_lab_instance`) for every other user, from the network topology, from the device count of the lab page and from the editor of any user who is neither the author nor an admin (who, in turn, see an eye icon on the node and a toggle in the device form). Hidden devices are always started with the lab even when the option to start the devices is disabled, and they never block the "Leave lab" button for the users who cannot see them. They are stopped and deleted along with the lab instance like any other device, and the worker, sandbox and export serializations are never filtered so an exported lab keeps its hidden devices. Lab imports and lab copies preserve the flag for their new author. The state notifications of a hidden device instance are only sent to the lab author, and the instance API denies view, start, stop, reset and logs of a hidden device instance to every user but its lab author and the admins.
- Device error alerts by e-mail: when a device instance reaches the `error` state (`InstanceStateMessage::STATE_ERROR` of type `device`), `InstanceStateMessageHandler` now notifies the contact address configured in `CONTACT_MAIL` through the new `DeviceErrorMailService` (`templates/emails/device_error.html.twig`), including the device name, its instance UUID, the lab, the owner, the state before the error, the failed action, the worker and the error message/code. The e-mail is best-effort: a mail failure is logged and never breaks the state message handling.
- Guest-owned instances are no longer shown with an empty owner: the invitation code of a guest instance is exposed in the `api_get_lab_instance` serialization group (`InvitationCode::$code`), so the instances page (`AllInstancesList`/`OptimizedInstanceList`) displays the code used to connect in place of the previous `Owner: N/A`, and the "Details" modal shows both the code and the guest e-mail address in the Owner card. User- and group-owned instances keep displaying the owner name.

### Bug fixes
- Device instances no longer stay stuck in `starting` forever when no state is ever received back from the worker (lost `InstanceStateMessage`, exception in a message handler, front update triggering a start resync): `DeviceInstance` now records the date of its last state change (`device_instance.state_changed_at`, migration `Version20261013120000`, updated by `setState()`), and the new `app:instance:reconcile-starting` command flags any device still in `starting` after `--min-age` minutes (default 10) as `error` and notifies its users so it can be started again; it runs every 5 minutes through the new `remotelabz-instance-reconcile.service`/`.timer` systemd units (see `bin/systemd/README.md`), with a `--dry-run` mode for dry checks. `InstanceStateMessageHandler` no longer crashes on a `STATE_STARTED` reply whose options are `null` (`array_key_exists()` replaced by `isset()`, so the "started successfully" notification is sent again), and `WorkerHandshakeMessageHandler` now rolls the device back to its previous state when serializing or dispatching the start message of the resync fails, instead of leaving it in `starting` with no message actually sent. The full analysis is documented in `doc/start-resync-state-started.md` (the worker-side fixes listed in its §5 are still to be done).

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