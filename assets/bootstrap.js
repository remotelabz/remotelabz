import { startStimulusApp } from '@symfony/stimulus-bridge';
import { registerReactControllerComponents } from '@symfony/ux-react';

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));

window.Stimulus = startStimulusApp(require.context('./controllers', true, /\.controller\.js$/));
