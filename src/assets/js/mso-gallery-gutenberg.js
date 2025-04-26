import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
    MediaUpload,
    MediaPlaceholder,
    InspectorControls,
    BlockControls
} from '@wordpress/block-editor';
import {
    Button,
    PanelBody,
    ToolbarGroup,
    ToolbarButton,
    Spinner
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

registerBlockType('mso/gallery', {
    title: __('MSO Gallery', 'mso-gallery'),
    icon: 'format-gallery',
    category: 'media',
    attributes: {
        ids: {
            type: 'array',
            default: [],
        },
    },

    edit: (props) => {
        const { attributes, setAttributes } = props;
        const { ids } = attributes;

        // --- Utiliser useSelect avec getEntityRecords ---
        const { media, isLoading } = useSelect( ( select ) => {
            const { getEntityRecords, hasFinishedResolution } = select( coreStore );
            const hasIds = ids && ids.length > 0;

            if (!hasIds) {
                return { media: [], isLoading: false };
            }

            // Définir la requête pour getEntityRecords
            // Le type d'entité est 'postType', le nom est 'attachment'
            const queryArgs = {
                include: ids,
                per_page: -1, // Récupérer tous les médias correspondants
                orderby: 'include', // Ordonner selon l'ordre des IDs fournis
                _embed: true // Optionnel: pour intégrer des données liées comme les tailles d'image
            };

            // Définir les arguments pour hasFinishedResolution
            // Ils doivent correspondre aux arguments passés à getEntityRecords
            const resolverArgs = [ 'postType', 'attachment', queryArgs ];

            return {
                // Appeler getEntityRecords avec le type, le nom, et la requête
                media: getEntityRecords( 'postType', 'attachment', queryArgs ),
                isLoading: ! hasFinishedResolution( 'getEntityRecords', resolverArgs ),
            };
        }, [ ids ] ); // La dépendance reste 'ids'

        // --- Fonctions onSelectImages et removeImage (inchangées) ---
        const onSelectImages = (newImages) => {
            const newIds = newImages.map((img) => img.id);
            setAttributes({ ids: newIds });
        };

        const removeImage = ( idToRemove ) => {
            const newIds = ids.filter( ( id ) => id !== idToRemove );
            setAttributes( { ids: newIds } );
        };

        const hasImages = ids.length > 0;

        // --- Rendu (légèrement ajusté pour la clarté) ---
        return (
            <div>
                <InspectorControls>
                    {/* ... (inchangé) ... */}
                    <PanelBody title={__('Gallery Settings', 'mso-gallery')}>
                        <p>{__('Select images for your gallery.', 'mso-gallery')}</p>
                        <MediaUpload
                            onSelect={onSelectImages}
                            allowedTypes={['image']}
                            multiple
                            gallery
                            value={ids}
                            render={({ open }) => (
                                <Button isPrimary onClick={open}>
                                    {hasImages ? __('Edit Gallery', 'mso-gallery') : __('Create Gallery', 'mso-gallery')}
                                </Button>
                            )}
                        />
                    </PanelBody>
                </InspectorControls>

                <BlockControls>
                    {/* ... (inchangé) ... */}
                    <ToolbarGroup>
                        <MediaUpload
                            onSelect={onSelectImages}
                            allowedTypes={['image']}
                            multiple
                            gallery
                            value={ids}
                            render={({ open }) => (
                                <ToolbarButton
                                    icon="edit"
                                    label={__('Edit Gallery', 'mso-gallery')}
                                    onClick={open}
                                />
                            )}
                        />
                    </ToolbarGroup>
                </BlockControls>

                {!hasImages && (
                    <MediaPlaceholder
                        /* ... (inchangé) ... */
                        icon="format-gallery"
                        labels={{
                            title: __('MSO Gallery', 'mso-gallery'),
                            instructions: __('Create your gallery by selecting images.', 'mso-gallery'),
                        }}
                        onSelect={onSelectImages}
                        allowedTypes={['image']}
                        multiple
                    />
                )}

                {hasImages && (
                    <div className="mso-gallery-preview" style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', marginTop: '10px' }}>
                        {isLoading && <Spinner />}
                        {!isLoading && media && media.length > 0 && media.map((img) => {
                            // L'URL de la miniature peut être dans _embedded si _embed=true est utilisé, sinon media_details
                            const thumbnailUrl = img._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url || img.media_details?.sizes?.thumbnail?.source_url || img.source_url;
                            return (
                                <div key={img.id} style={{ position: 'relative', border: '1px solid #ddd' }}>
                                    {thumbnailUrl ? (
                                        <img
                                            src={thumbnailUrl}
                                            alt={img.alt_text || ''}
                                            style={{ maxWidth: '100px', height: 'auto', display: 'block' }}
                                        />
                                    ) : (
                                        <span style={{ display: 'inline-block', padding: '10px', background: '#eee', fontSize: '10px' }}>
                                            {__('Loading...', 'mso-gallery')} ID: {img.id}
                                        </span>
                                    )}
                                    <Button
                                        icon="no-alt"
                                        label={__('Remove image', 'mso-gallery')}
                                        onClick={() => removeImage(img.id)}
                                        isDestructive
                                        style={{
                                            position: 'absolute', top: '-8px', right: '-8px', background: 'white',
                                            borderRadius: '50%', padding: '0', minWidth: '18px', height: '18px',
                                            boxShadow: '0 0 2px rgba(0,0,0,0.5)'
                                        }}
                                    />
                                </div>
                            );
                        })}
                        {!isLoading && (!media || media.length === 0) && (
                            <p>{__('Could not load image previews. Check browser console or REST API availability.', 'mso-gallery')}</p>
                        )}
                    </div>
                )}
            </div>
        );
    },

    save: () => {
        return null;
    },
});