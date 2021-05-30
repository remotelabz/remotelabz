// register react components
import ReactOnRails from 'react-on-rails';
import InstanceOwnerSelect from './Instances/InstanceOwnerSelect';
import UserSelect from './Form/UserSelect';
import GroupImport from './Form/GroupImport';
import GroupSelect from './Form/GroupSelect';
import GroupRoleSelect from './Form/GroupRoleSelect';
import GroupExplorer from './Groups/GroupExplorer';
import InstanceManager from './Instances/InstanceManager';
import LabImporter from './Lab/LabImporter';
import SandboxManager from './Sandbox/SandboxManager';

ReactOnRails.register({ InstanceOwnerSelect, UserSelect, GroupExplorer, GroupImport, GroupSelect, GroupRoleSelect, InstanceManager, LabImporter, SandboxManager });