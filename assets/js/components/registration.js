// register react components
import ReactOnRails from 'react-on-rails';
import InstanceOwnerSelector from './Instances/InstanceOwnerSelector';
import UserSelect from './Form/UserSelect';
import GroupRoleSelect from './Form/GroupRoleSelect';

ReactOnRails.register({ InstanceOwnerSelector, UserSelect });
ReactOnRails.register({ InstanceOwnerSelector, GroupRoleSelect });