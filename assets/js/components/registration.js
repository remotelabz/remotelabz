// register react components
import ReactOnRails from 'react-on-rails';
import InstanceOwnerSelect from './Instances/InstanceOwnerSelect';
import UserSelect from './Form/UserSelect';
import GroupImport from './Form/GroupImport';
import GroupSelect from './Form/GroupSelect';
import LabSelect from './Form/LabSelect';
import GroupRoleSelect from './Form/GroupRoleSelect';
import GroupExplorer from './Groups/GroupExplorer';
import InstanceManager from './Instances/InstanceManager';
import AllInstancesManager from './Instances/AllInstancesManager';
import LabImporter from './Lab/LabImporter';
import InstanceList from './Instances/InstanceList';
import Editor from './Editor/Editor';
import SandboxManager from './Sandbox/SandboxManager';

ReactOnRails.register({
    InstanceOwnerSelect,
    UserSelect,
    GroupExplorer,
    GroupImport,
    GroupSelect,
    GroupRoleSelect,
    InstanceManager,
    InstanceList,
    LabImporter,
    Editor,
    SandboxManager,
    AllInstancesManager,
    LabSelect
});
