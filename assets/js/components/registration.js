// register react components
import ReactOnRails from 'react-on-rails';
import InstanceOwnerSelector from './Instances/InstanceOwnerSelector';
import UserSelect from './Form/UserSelect';
import GroupSelect from './Form/GroupSelect';
import GroupRoleSelect from './Form/GroupRoleSelect';
import GroupExplorer from './Groups/GroupExplorer';

ReactOnRails.register({ InstanceOwnerSelector, UserSelect, GroupSelect, GroupRoleSelect, GroupExplorer });