// register react components
import ReactOnRails from 'react-on-rails';
import InstanceOwnerSelect from './Instances/InstanceOwnerSelect';
import UserSelect from './Form/UserSelect';
import GroupSelect from './Form/GroupSelect';
import GroupRoleSelect from './Form/GroupRoleSelect';
import GroupExplorer from './Groups/GroupExplorer';
import InstanceManager from './Instances/InstanceManager';

ReactOnRails.register({ InstanceOwnerSelect, UserSelect, GroupSelect, GroupRoleSelect, GroupExplorer, InstanceManager });