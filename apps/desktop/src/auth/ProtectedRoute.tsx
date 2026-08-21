import {
    Navigate,
    Outlet,
} from 'react-router';

import LoadingScreen
    from '../components/LoadingScreen';

import type { UserRole }
    from '../types/auth';

import { getRoleHome }
    from './roleHome';

import { useAuth }
    from './AuthContext';

interface ProtectedRouteProps {
    allowedRoles: UserRole[];
}

export default function ProtectedRoute({
    allowedRoles,
}: ProtectedRouteProps) {
    const {
        user,
        isLoading,
        isAuthenticated,
    } = useAuth();

    if (isLoading) {
        return <LoadingScreen />;
    }

    if (
        !isAuthenticated ||
        !user
    ) {
        return (
            <Navigate
                to="/login"
                replace
            />
        );
    }

    if (
        !allowedRoles.includes(
            user.role,
        )
    ) {
        return (
            <Navigate
                to={getRoleHome(user.role)}
                replace
            />
        );
    }

    return <Outlet />;
}