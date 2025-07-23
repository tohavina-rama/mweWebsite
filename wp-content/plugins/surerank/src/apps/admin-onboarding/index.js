import {
	createRootRoute,
	createRoute,
	createHashHistory,
	RouterProvider,
	createRouter,
} from '@tanstack/react-router';
import { mountComponent } from '@Functions/utils';
import WebsiteDetails from '@Onboarding/steps/website-details';
import UserDetails from '@Onboarding/steps/user-details';
import Welcome from '@Onboarding/steps/welcome';
// import ImportData from '@Onboarding/steps/import-data';
import Success from '@Onboarding/steps/success';
import SocialProfiles from '@Onboarding/steps/social-profiles';
import OnboardingLayout from '@Onboarding/components/layout/onboarding-layout';

// App styles
import './style.scss';

// Global styles
import '@Global/style.scss';

export const ONBOARDING_STEPS_CONFIG = [
	{
		path: '/',
		component: Welcome,
		config: {
			containerSize: 'sm',
		},
	},
	// {
	// 	path: '/import-data',
	// 	component: ImportData,
	// 	config: {
	// 		containerSize: 'lg',
	// 		hideBackButton: true,
	// 	},
	// },
	{
		path: '/website-details',
		component: WebsiteDetails,
		config: {
			containerSize: 'lg',
		},
	},
	{
		path: '/social-profiles',
		component: SocialProfiles,
		config: {
			containerSize: 'lg',
		},
	},
	{
		path: '/user-details',
		component: UserDetails,
		config: {
			containerSize: 'lg',
		},
	},
	{
		path: '/finish',
		component: Success,
		config: {
			containerSize: 'lg',
		},
	},
];

const createRoutes = ( stepsConfig ) => {
	const rootRoute = createRootRoute( {
		component: OnboardingLayout,
	} );

	const routes = stepsConfig.map( ( step ) => {
		return createRoute( {
			getParentRoute: () => rootRoute,
			path: step.path,
			component: step.component,
		} );
	} );

	const routeTree = rootRoute.addChildren( routes );

	return createRouter( {
		routeTree,
		history: createHashHistory(),
	} );
};

const router = createRoutes( ONBOARDING_STEPS_CONFIG );

mountComponent( '#surerank-root', <RouterProvider router={ router } /> );
