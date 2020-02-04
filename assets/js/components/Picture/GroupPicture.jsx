import React, {Component} from 'react';
import Routing from 'fos-jsrouting';

export default class GroupPicture extends Component {
    constructor(props) {
        super(props);
    }

    render() {
        let group = this.props.group;
        return (<>
            { group.picture !== undefined ?
                <div>
                    <img src={Routing.generate("get_group_picture", {slug: group.path, size: this.props.size, hash: Date.now()})} className={(this.props.className || "") + " " + (this.props.rounded ? "rounded-lg " : "") + (this.props.circled ? "rounded-circle " : "") + (this.props.size ? "s" + this.props.size : "")}></img>
                </div>
                :
                <div className={(this.props.className || "") + " avatar identicon bg-" + (group.id % 8 + 1) + " " + (this.props.rounded ? "rounded-lg " : "") + (this.props.circled ? "rounded-circle " : "") + (this.props.size ? "s" + this.props.size : "")}>
                    {group.name.charAt(0).toUpperCase()}
                </div>
            }
        </>)
    }
}