import ChatWindow from './ChatWindow';

/**
 * Full-page chat for the separate browser window served at /labs/chat/{labUuid}.
 * The window is closed with the "x" button (window.close).
 */
export default function StandaloneChat({ lab, user }) {
    return (
        <ChatWindow
            show
            variant="standalone"
            lab={lab}
            user={user}
            onHide={() => window.close()}
        />
    );
}
