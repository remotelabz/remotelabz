import React, { Component } from 'react';
import { components } from 'react-select';
import Select from 'react-select';
import API from '../../api';
import SVG from '../Display/SVG';
import Routing from 'fos-jsrouting';
import {Tooltip, OverlayTrigger, Badge} from 'react-bootstrap';
import { Group, getGroupIdenticonClass, getGroupPath } from '../Groups';
import { GroupRoleLabel } from '../Groups/Groups';

const api = API.getInstance();

const getPath = (group) => {
    /** @var {String} path */
    let path = '';
    while (undefined !== group.parent) {
        path = group.parent.name + ' / ' + path;
        group = group.parent;
    }

    return path;
};

const ValueContainer = ({ children, ...props }) => (
  <components.ValueContainer {...props}>{children}</components.ValueContainer>
);

const Option = props => {
    /** @type {Group} group */
    const group = props.data;
    return (
        <components.Option {...props}>
            <div className="d-flex">
                <div className="mr-2">
                    {props.data.type && props.data.type == 'user' ?
                        <div className="s36 mr-2">
                            <img src={"/users/" + props.data.value + "/picture?size=36"} className="rounded-circle"></img>
                        </div>
                        :
                        <div className={`avatar identicon s36 rounded mr-2 ${getGroupIdenticonClass(props.data)}`}>{props.data.name.charAt(0).toUpperCase()}</div>
                    }
                </div>
                <div className="d-flex flex-column">
                    <div style={{lineHeight: 16 + 'px'}}>{getGroupPath(group)} <span className="fw600">{props.data.name}
                        {(props.data.type && props.data.type == 'group') &&
                            <Badge variant="default" className="ml-2">{GroupRoleLabel[props.data.role]}</Badge>
                        }
                    </span>{ props.data.hasLabInstance && <Badge variant="success" className="ml-2">Joined</Badge> }</div>

                    {props.data.owner &&
                        <div>Owned by <img src={"/users/" + props.data.owner.id + "/picture?size=16"} className="rounded-circle"></img> {props.data.owner.name}</div>
                    }
                </div>
                <div className="d-flex flex-grow-1"></div>
                {(props.data.type && props.data.type == 'group') &&
                    <div className="d-flex align-items-center">
                        <OverlayTrigger placement="bottom" overlay={<Tooltip>Subgroups</Tooltip>}>
                            <div className="mr-2"><SVG name="folder-o"></SVG> {props.data.children.length}</div>
                        </OverlayTrigger>
                        <OverlayTrigger placement="bottom" overlay={<Tooltip>Members</Tooltip>}>
                            <div><SVG name="users"></SVG> {props.data.usersCount}</div>
                        </OverlayTrigger>
                    </div>
                }
            </div>
        </components.Option>
    );
};

const SingleValue = ({ ...props }) => (
    <components.SingleValue {...props} className="d-flex align-items-center">
        {props.data.type && props.data.type == 'user' ?
            <div className="s24 mr-2">
                <img src={"/users/" + props.data.value + "/picture?size=24"} className="rounded-circle"></img>
            </div>
            :
            <div className={"avatar identicon bg-" + (props.data.id % 8 + 1) + " s24 rounded mr-2"} style={{fontSize: 12 + 'px'}}>{props.data.name.charAt(0).toUpperCase()}</div>
        }
        <div>
            {getPath(props.data)} <span className="fw600">{ props.data.name }</span>
        </div>
    </components.SingleValue>
);

export default class InstanceOwnerSelect extends Component {
    constructor(props) {
        super(props);
    }

    loadOptions = (inputValue) => {
        return api.get(Routing.generate('api_groups'), {
            params: {
                search: inputValue,
                context: 'group_explore'
            }
        })
        .then(response => {
            return response.data.map(group => {
                return {...group, value: group.id, label: group.name};
            });
        });
    }

    render() {
        return (
            <Select
                options={this.props.options}
                className={'react-select-container ' + (this.props.className || "")}
                classNamePrefix="react-select"
                placeholder={this.props.placeholder || "Search for a group"}
                components={{ ValueContainer, Option, SingleValue }}
                name={this.props.fieldName || "_group"}
                isClearable={this.props.isClearable || false}
                {...this.props}
            />
        );
    }
}