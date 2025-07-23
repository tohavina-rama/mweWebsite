import { Drawer, Container, Badge, Button } from '@bsf/force-ui';
import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSuspenseSiteSeoAnalysis } from './site-seo-checks-main';
import {
	getSeverityColor,
	getSeverityLabel,
} from '@GlobalComponents/seo-checks';
import FixButton from '@GlobalComponents/fix-button';
import DOMPurify from 'dompurify';
import { LockIcon } from 'lucide-react';
import { isURL } from '@/functions/utils';
import { ImageGrid } from '@/global/components/check-card';

const SiteSeoChecksDrawer = () => {
	// Using suspense version inside Suspense boundary
	const [ { open, selectedItem = {} }, dispatch ] =
		useSuspenseSiteSeoAnalysis();

	const handleSetDrawerOpen = ( value ) => {
		dispatch( {
			open: value,
		} );
	};

	// Render the description as a list or paragraph
	const renderDescription = useCallback(
		( list, type = 'paragraph', isImage = false ) => {
			if ( ! list || list?.length <= 0 ) {
				return;
			}

			if ( isImage ) {
				return (
					<div className="my-4">
						<ImageGrid images={ list } />
					</div>
				);
			}
			if ( type === 'list' ) {
				const listContent = list.map( ( item ) => {
					if ( isURL( item ) ) {
						return (
							<li
								className="m-0 text-text-primary mb-[2px]"
								key={ item }
							>
								<Button
									className="no-underline hover:no-underline focus:[box-shadow:none] font-normal"
									variant="link"
									tag="a"
									href={ item }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ item }
								</Button>
							</li>
						);
					}
					return (
						<li
							className="m-0 text-text-primary mb-[2px]"
							key={ item }
							dangerouslySetInnerHTML={ {
								__html: DOMPurify.sanitize( item ),
							} }
						/>
					);
				} );
				return (
					<ul className="my-0 ml-2 mr-0 text-text-primary list-disc list-inside">
						{ listContent }
					</ul>
				);
			}

			if ( typeof list === 'string' && type === 'paragraph' ) {
				return (
					<p
						className="m-0 text-text-primary"
						dangerouslySetInnerHTML={ {
							__html: DOMPurify.sanitize( list ),
						} }
					/>
				);
			}

			return (
				<div className="flex flex-col gap-y-2 pt-2 pb-2">
					{ list.map( ( item, idx ) => {
						if (
							typeof item === 'object' &&
							Array.isArray( item.list )
						) {
							const nextItem = list[ idx + 1 ];
							const isImgFlag =
								nextItem &&
								typeof nextItem === 'object' &&
								( nextItem?.img === true ||
									nextItem?.img === 'true' );

							return (
								<div key={ idx }>
									{ renderDescription(
										item.list,
										'list',
										isImgFlag
									) }
								</div>
							);
						}

						// Skip img flag objects from rendering they are handled in the image grid
						if ( typeof item === 'object' && item.img ) {
							return null;
						}

						return (
							<p
								className="m-0 text-text-primary text-sm font-normal [&_a]:no-underline [&_a]:ring-0"
								key={ idx }
								dangerouslySetInnerHTML={ {
									__html: DOMPurify.sanitize( item ),
								} }
							/>
						);
					} ) }
				</div>
			);
		},
		[]
	);

	return (
		<Drawer
			exitOnEsc
			position="right"
			scrollLock
			setOpen={ handleSetDrawerOpen }
			open={ open }
			className="z-999999"
			exitOnClickOutside
		>
			<Drawer.Panel>
				<Drawer.Header>
					<Container justify="between">
						<Drawer.Title>
							{ __( 'Site Analysis', 'surerank' ) }
						</Drawer.Title>
						<div className="inline-flex items-center gap-2">
							<Badge
								size="xs"
								label={ getSeverityLabel(
									selectedItem?.status
								) }
								variant={ getSeverityColor(
									selectedItem?.status
								) }
							/>
							<Drawer.CloseButton />
						</div>
					</Container>
					<Drawer.Description>
						{ selectedItem?.message }
					</Drawer.Description>
				</Drawer.Header>
				<Drawer.Body className="overflow-x-hidden space-y-3">
					<div className="px-2 space-y-0.5 w-full border border-border-subtle border-solid rounded-md bg-background-secondary">
						{ renderDescription( selectedItem?.description ) || (
							<p className="m-0 text-text-secondary">
								{ __(
									'No additional information to show.',
									'surerank'
								) }
							</p>
						) }
					</div>
					<FixButton
						button_label={
							selectedItem?.not_fixable
								? __( 'Fix it for me', 'surerank' )
								: __( 'Help Me Fix', 'surerank' )
						}
						icon={ <LockIcon /> }
						size="sm"
						tooltipProps={ { className: 'z-999999' } }
					/>
				</Drawer.Body>
			</Drawer.Panel>
			<Drawer.Backdrop />
		</Drawer>
	);
};

export default SiteSeoChecksDrawer;
