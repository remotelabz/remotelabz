import React, { useState, useEffect} from 'react';
import { components } from 'react-select';
import AsyncSelect from 'react-select/async';
import SVG from '../Display/SVG';
import {Tooltip, OverlayTrigger, Badge} from 'react-bootstrap';
import { Group, getGroupIdenticonClass } from '../Groups';
import { GroupRoleLabel } from '../Groups/Groups';
import Remotelabz from '../API';

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
    // if (Array.isArray(group.fqn))
    //     group.fqn.pop()
    // const fqn = group.fqn.reduce((acc, cur, i, array) => {
    //     if (i >= array.length - 1) {
    //         return null
    //     }

    //     return acc + cur + ' / ';
    // }, '');
    // console.log(fqn)
    return (
        <components.Option {...props}>
            <div className="d-flex">
                <div className="mr-2">
                    {props.data.type && props.data.type == 'user' ?
                        <div className="s36 mr-2">
                            <img src={"/users/" + props.data.id + "/picture?size=36"} className="rounded-circle"></img>
                        </div>
                        :
                        <div className={`avatar identicon s36 rounded mr-2 ${getGroupIdenticonClass(props.data)}`}>{props.data.name.charAt(0).toUpperCase()}</div>
                    }
                </div>
                <div className="d-flex flex-column">
                    <div style={{ lineHeight: 16 + 'px' }}>{props.data.fullyQualifiedName} <span className="fw600">{props.data.name}
                        {(props.data.type && props.data.type == 'group') &&
                            <Badge variant="default" className="ml-2">{GroupRoleLabel[props.data.role]}</Badge>
                        }
                    </span>{ props.data.hasLabInstance && <Badge variant="success" className="ml-2">Joined</Badge> }</div>

                </div>
                <div className="d-flex flex-grow-1"></div>
                {(props.data.type && props.data.type == 'group') &&
                    <div className="d-flex align-items-center">
                     
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
            <div className={"avatar identicon bg-" + (props.data.value % 8 + 1) + " s24 rounded mr-2"} style={{fontSize: 12 + 'px'}}>{props.data.label.charAt(0).toUpperCase()}</div>
        }
        <div>
            {getPath(props.data)} <span className="fw600">{ props.data.label }</span>
        </div>
    </components.SingleValue>
);

// function mergeChildren(group, parent) {
//     group.fqn = (Array.isArray(parent.fqn) ? [...parent.fqn, group.name] : [group.name])

//     return group.children.reduce((accumulator, child) => {
//         child.fqn = (Array.isArray(group.fqn) ? [...group.fqn, child.name] : [child.name])
//         return accumulator.concat(child.children.length > 0 ? mergeChildren(child, group) : child)
//     }, [group])
// }

export default function InstanceOwnerSelect(props = {user: {}, className: '', placeholder: '', fieldName: '', isClearable: false}) {
    const [user, setUser] = useState(props.user);

    function loadOptions(input) {
        return new Promise(resolve => {
            Remotelabz.users.get(props.user)
                .then(response => {
                    const user = { ...(response.data), type: 'user', value: response.data.id, label: response.data.name, fqn: [] }
                    Remotelabz.groups.all(input, 10, 1, false)
                    .then(response => {
                        const groups = response.data;
                        let groupOptions = [];

                        if (Array.isArray(groups)) {
                            for (const group of groups) {
                                group.fullyQualifiedName.pop()
                                group.fullyQualifiedName = group.fullyQualifiedName.reduce((acc, cur) => acc + cur + ' / ', '')
                                groupOptions.push({
                                    ...group,
                                    value: group.id,
                                    label: group.name,
                                    type: 'group',
                                    owner: group.users.find(user => user.role === 'owner')
                                })
                            }
                        }

                        const data = [{
                            label: 'User',
                            options: [user]
                        }, {
                            label: 'Groups',
                            options: groupOptions
                            }];
                        resolve(data)
                    });
                })
        })
        
    }

    return (
        <AsyncSelect
            defaultOptions
            loadOptions={loadOptions}
            cacheOptions
            defaultValue={user.uuid}
            className={'react-select-container ' + (props.className || "")}
            classNamePrefix="react-select"
            placeholder={props.placeholder || "Search for a group"}
            components={{ ValueContainer, Option, SingleValue }}
            name={props.fieldName || "_group"}
            isClearable={props.isClearable || false}
            {...props}
        />
    );
}