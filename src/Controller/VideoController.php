<?php

namespace App\Controller;

use App\DTOs\ComentarioDTO;
use App\DTOs\PrivacidadUsuarioDTO;
use App\DTOs\RespuestaDTO;
use App\DTOs\VideoDTO;
use App\DTOs\ValoracionGlobalDTO;
use App\Entity\PrivacidadUsuario;
use App\Repository\ComentarioRepository;
use App\Repository\PrivacidadUsuarioRepository;
use App\Repository\RespuestaRepository;
use App\Repository\ValoracionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\VideoRepository;
use App\Entity\Video;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Usuario;
use App\Entity\Tematica;

#[Route('/api/video')]
class VideoController extends AbstractController
{
    #[Route('', name: 'lista_video', methods: ['GET'])]
    public function list(VideoRepository $videoRepository, ValoracionRepository $valoracionRepository, PrivacidadUsuarioRepository $privacidadUsuarioRepository): JsonResponse
    {
        $listaVideos = $videoRepository->findAll();

        $listaVideosDTO=[];

        foreach ($listaVideos as $video){
            $videoDTO = new VideoDTO();
            $videoDTO -> setId($video->getId());
            $videoDTO ->setTitulo($video->getTitulo());
            $videoDTO ->setDescripcion($video->getDescripcion());
            $videoDTO ->setUrl($video->getUrl());
            $videoDTO ->setCanal($video->getCanal()->getNombreCanal());
            $videoDTO->setTematica($video->getTematica()->getTematica());

            $visualizacion = $valoracionRepository->visualizacionTotal($videoDTO->getId());
            $like = $valoracionRepository->favTotal($videoDTO->getId());
            $dislike = $valoracionRepository->dislikeTotal($videoDTO->getId());
            $visualizacionesDTO = [];

            $valoraciones = new ValoracionGlobalDTO();
            $valoraciones->setVisualizacion($visualizacion[0]['visualizacion']);
            $valoraciones->setFav($like[0]['fav']);
            $valoraciones->setDislike($dislike[0]['dislike']);
            $visualizacionesDTO[] = $valoraciones;

            $videoDTO->setValoracionGlobalDTO($visualizacionesDTO);

            $privacidadDelCanal = $privacidadUsuarioRepository->getByCanal($videoDTO->getCanal());
            $privacidadCanalDTO = []; //lista para almacenar la privacidad de cada video

            $privacidadCanal= new PrivacidadUsuarioDTO();
            $privacidadCanal->setIsPublico($privacidadDelCanal[0]['is_publico']);
//            $privacidadCanal->setPermitirSuscripciones($privacidadDelCanal[0]['permitir_suscripciones']);
            $privacidadCanal->setPermitirDescargar($privacidadDelCanal[0]['permitir_descargar']);
            $privacidadCanalDTO[]= $privacidadCanal;

            $videoDTO->setPrivacidadDTO($privacidadCanalDTO);

            $listaVideosDTO[]=$videoDTO;
        }
        return $this->json($listaVideosDTO, Response::HTTP_OK);
    }

    #[Route('', name: 'crear_video', methods: ['POST'])]
    public function crearvideo (EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $data =json_decode($request->getContent(),true);

        $nuevoVideo = new Video();
        $nuevoVideo->setTitulo($data['titulo']);
        $nuevoVideo->setDescripcion($data['descripcion']);
        $nuevoVideo->setUrl($data['url']);

        $usuario = $entityManager->getRepository(Usuario::class)->findBy(["id" => $data['canal']]);
        $nuevoVideo->setCanal($usuario[0]);
        $tematica =$entityManager->getRepository(Tematica::class)->findBy(["id" => $data['tematica']]);
        $nuevoVideo->setTematica($tematica[0]);

        $entityManager->persist($nuevoVideo);
        $entityManager->flush();

        return $this->json(['message' => 'Video creado'], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: "editar_video", methods: ["PUT"])]
    public function editarvideo (EntityManagerInterface $entityManager, Request $request, $id):JsonResponse
    {
        $data = json_decode($request-> getContent(), true);

        $video = $entityManager->getRepository(Video::class)->find($id);
        if (!$video) {
            return $this->json(['message' => 'Video no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $video->setTitulo($data['titulo']);
        $video->setDescripcion($data['descripcion']);
        $video->setUrl($data['url']);

        $usuario = $entityManager->getRepository(Usuario::class)->findBy(["id" => $data['canal']]);
        $video->setCanal($usuario[0]);
        $tematica =$entityManager->getRepository(Tematica::class)->findBy(["id" => $data['tematica']]);
        $video->setTematica($tematica[0]);

        $entityManager->flush();

        return $this->json(['message' => 'Video modificado'], Response::HTTP_OK);

    }
    #[Route('/{id}', name: "delete_by_id", methods: ["DELETE"])]
    public function deleteById(EntityManagerInterface $entityManager, $id):JsonResponse
    {

        $video = $entityManager->getRepository(Video::class)->find($id);

        $entityManager->remove($video);
        $entityManager->flush();

        return $this->json(['message' => 'Video eliminado'], Response::HTTP_OK);

    }
    //video con comentarios y respuestas
    #[Route('/listarId/{id}', name: "listarVideosPorId", methods: ["GET"])]
    public function videoID(ValoracionRepository $valoracionRepository, VideoRepository $videoRepository, int $id, ComentarioRepository $comentarioRepository,RespuestaRepository $respuestaRepository): JsonResponse
    {

        $video = $videoRepository ->buscarvideoID($id);
        $comentarios = $comentarioRepository->comentariovideoID($id);


        $videoDTO = new VideoDTO();
        $videoDTO->setId($video->getId());
        $videoDTO->setTitulo($video->getTitulo());
        $videoDTO->setDescripcion($video->getDescripcion());
        $videoDTO->setUrl($video->getUrl());
        $videoDTO->setCanal($video->getCanal()->getNombreCanal());
        $videoDTO->setTematica($video->getTematica()->getTematica());


        $comentariosDTO = [];
        foreach ($comentarios as $comentario) {
            $comentarioDTO = new ComentarioDTO();
            $comentarioDTO->setId($comentario['id']);
            $id_comentario= $comentario['id'];
            $comentarioDTO->setFav($comentario['fav']);
            $comentarioDTO->setUsuario($comentario['username']);
            $comentarioDTO->setComentario($comentario['comentario']);
            $comentarioDTO->setDislike($comentario['dislike']);
            $respuestas =$respuestaRepository->respuestavideoID($id, $id_comentario);
            $resDTO = [];
            foreach ($respuestas as $respuesta){
                $respuestaDTO = new RespuestaDTO();
                $respuestaDTO->setId($respuesta['respuesta_id']);
                $respuestaDTO->setUsuario($respuesta['respuesta_username']);
                $respuestaDTO->setMensaje($respuesta['mensaje']);
                $resDTO[] = $respuestaDTO;

            }

            $comentarioDTO->setRespuesta($resDTO);

            $comentariosDTO[] = $comentarioDTO;
        }

        $videoDTO->setComentarioDTO($comentariosDTO);

        $visualizacion = $valoracionRepository->visualizacionTotal($id);
        $like = $valoracionRepository->favTotal($id);
        $dislike = $valoracionRepository->dislikeTotal($id);


        $valoraciones = new ValoracionGlobalDTO();
        $valoraciones->setVisualizacion($visualizacion[0]['visualizacion']);
        $valoraciones->setFav($like[0]['fav']);
        $valoraciones->setDislike($dislike[0]['dislike']);
        $visualizacionesDTO[] = $valoraciones;

        $videoDTO->setValoracionGlobalDTO($visualizacionesDTO);

        return $this->json($videoDTO, Response::HTTP_OK);
    }

    //videos de tus suscripciones que solo trae dos
    #[Route('/suscripcionesDos/{id}', name: 'videossuscripcion2', methods: ['GET'])]
    public function listSuscripcionDos(VideoRepository $videoRepository, Request $request, int $id) :JsonResponse
    {
        $suscripciones = $videoRepository->buscarvideosuscripcion($id);
        dump($suscripciones);
        return  $this->json($suscripciones, Response::HTTP_OK);
    }

    //videos por tematicas SOLO DOS
    #[Route('/tematica/{id}', name: 'videostematica1', methods: ['GET'])]
    public function listByTematica(VideoRepository $videoRepository, Request $request, int $id)
    {
        $temas = $videoRepository->buscarvideotematica($id);
        dump($temas);
        return $this->json($temas, Response::HTTP_OK);
    }

    //video que busca tematica por texto
    #[Route('/tematica/nombre/{tematica}', name: 'videostematica', methods: ['GET'])]
    public function buscarvideotitulotematica(ValoracionRepository $valoracionRepository,VideoRepository $videoRepository, Request $request, string $tematica)
    {
        $temas = $videoRepository->buscarvideotitulotematica($tematica);

        foreach ($temas as &$subs) {
            $visualizacion = $valoracionRepository->visualizacionTotal($subs['id']);
            $like = $valoracionRepository->favTotal($subs['id']);
            $dislike = $valoracionRepository->dislikeTotal($subs['id']);

            $valoraciones = new ValoracionGlobalDTO();
            $valoraciones->setVisualizacion($visualizacion[0]['visualizacion']);
            $valoraciones->setFav($like[0]['fav']);
            $valoraciones->setDislike($dislike[0]['dislike']);

            // Agregar los valores de visualización, favoritos y disgustos al elemento actual de suscripciones
            $subs['visualizacion'] = $valoraciones->getVisualizacion();
            $subs['fav'] = $valoraciones->getFav();
            $subs['dislike'] = $valoraciones->getDislike();
        }




        dump($temas);
        return $this->json($temas, Response::HTTP_OK);
    }

    //las suscripciones y las sugerencias de tematica juntas PARA LAS TARJETAS
    #[Route('/sugerencias/{idSuscripcion}/{tematica}', name: 'lista_sugerencias', methods: ['GET'])]
    public function listasugerencias(VideoRepository $videoRepository, int $idSuscripcion, string $tematica): JsonResponse
    {
        $videosSuscripcion = $videoRepository->buscarvideosuscripcion($idSuscripcion);
        $videosTematica = $videoRepository->buscarvideotitulotematica($tematica);

        $lista=[];

        foreach ($videosSuscripcion as $video) {
            // Crear un array asociativo que represente un video con su imagen
            $videoConImagen = [
                'id' => $video['id'],
                'titulo' => $video['titulo'],
                'descripcion' => $video['descripcion'],
                'url' => $video['url'],
                'nombre_canal' => $video['nombre_canal'],
                'tematica' => $video['tematica'],
                'imagen' => $video['imagen']
            ];

            // Agregar el video al array lista
            $lista[] = $videoConImagen;
        }

        foreach ($videosTematica as $video) {
            // Crear un array asociativo que represente un video con su imagen
            $videoConImagen = [
                'id' => $video['id'],
                'titulo' => $video['titulo'],
                'descripcion' => $video['descripcion'],
                'url' => $video['url'],
                'nombre_canal' => $video['nombre_canal'],
                'tematica' => $video['tematica'],
                'imagen' => $video['imagen']
            ];

            // Agregar el video al array lista
            $lista[] = $videoConImagen;
        }
        //$response = [
        //    'videos_suscripcion' => $videosSuscripcion,
         //   'videos_tematica' => $videosTematica
       // ];

        return $this->json($lista, Response::HTTP_OK);
    }

    //trae el id del usuario que sube el video
    #[Route('/usuario/{id}', name: 'usuariovideo', methods: ['GET'])]
    public function usuarioVideoId(VideoRepository $videoRepository, Request $request, int $id)
    {
        $usuario = $videoRepository->cogerIdUsuarioVideo($id);
        dump($usuario);
        return $this->json($usuario, Response::HTTP_OK);
    }

    //trae todos los videos de las suscripciones que tenga el usuario
    #[Route('/videosSuscripciones/{id}', name: 'videossuscripcion', methods: ['GET'])]
    public function listTodoBySuscripcion(ValoracionRepository $valoracionRepository, VideoRepository $videoRepository, int $id): JsonResponse
    {
        $suscripciones = $videoRepository->buscarTodosVideosSuscripcion($id);


        foreach ($suscripciones as &$subs) {
            $visualizacion = $valoracionRepository->visualizacionTotal($subs['id']);
            $like = $valoracionRepository->favTotal($subs['id']);
            $dislike = $valoracionRepository->dislikeTotal($subs['id']);

            $valoraciones = new ValoracionGlobalDTO();
            $valoraciones->setVisualizacion($visualizacion[0]['visualizacion']);
            $valoraciones->setFav($like[0]['fav']);
            $valoraciones->setDislike($dislike[0]['dislike']);

            // Agregar los valores de visualización, favoritos y disgustos al elemento actual de suscripciones
            $subs['visualizacion'] = $valoraciones->getVisualizacion();
            $subs['fav'] = $valoraciones->getFav();
            $subs['dislike'] = $valoraciones->getDislike();
        }

        return $this->json($suscripciones, Response::HTTP_OK);
    }


    //Lista por titulos
    #[Route('/listarTitulo', name: 'titulos', methods: ['GET'])]
    public function getTitulo(VideoRepository $videoRepository, ValoracionRepository $valoracionRepository, Request $request): JsonResponse
    {
        $titulo = $request->query->get('titulo');

        $titulos = $videoRepository->buscarTitulos($titulo);

        $titulosDTO = [];
        foreach ($titulos as $video) {
            $videoDTO = new VideoDTO();
            $videoDTO ->setId($video['id']);
            $videoDTO ->setTitulo($video['titulo']);
            $videoDTO ->setDescripcion($video['descripcion']);
            $videoDTO ->setUrl($video['url']);
            $videoDTO ->setCanal($video['nombre_canal']);
            $videoDTO->setTematica($video['id_tematica']);

            $visualizacion = $valoracionRepository->visualizacionTotal($videoDTO->getId());
            $like = $valoracionRepository->favTotal($videoDTO->getId());
            $dislike = $valoracionRepository->dislikeTotal($videoDTO->getId());
            $visualizacionesDTO = [];

            $valoraciones = new ValoracionGlobalDTO();
            $valoraciones->setVisualizacion($visualizacion[0]['visualizacion']);
            $valoraciones->setFav($like[0]['fav']);
            $valoraciones->setDislike($dislike[0]['dislike']);
            $visualizacionesDTO[] = $valoraciones;

            $videoDTO->setValoracionGlobalDTO($visualizacionesDTO);

            $titulosDTO[]=$videoDTO;
        }

        return $this->json($titulosDTO, Response::HTTP_OK);
    }


    //Lista por canales
    #[Route('/listarCanales', name: 'canales', methods: ['GET'])]
    public function getCanales(VideoRepository $videoRepository, Request $request): JsonResponse
    {
        $canales = $request->query->get('nombre_canal');

        $canal = $videoRepository->buscarCanal($canales);

        dump($canal);

        return $this->json($canal, Response::HTTP_OK);
    }

    #[Route('/canalId/{id}', name: 'getVideosByIDCanal', methods: ["GET"])]
    public function getVideosByIDCanal(VideoRepository $videoRepository, int $id, ValoracionRepository $valoracionRepository):JsonResponse
    {
        $videos = $videoRepository -> getVideosByIDCanal($id);
        $listaVideosDTO=[];

        foreach ($videos as $video){
            $videoDTO = new videoDTO();
            $videoDTO ->setId($video['id']);
            $videoDTO ->setTitulo($video['titulo']);
            $videoDTO ->setDescripcion($video['descripcion']);
            $videoDTO ->setUrl($video['url']);
            $videoDTO ->setCanal($video['nombre_canal']);
            $videoDTO->setTematica($video['id_tematica']);

            $visualizacion = $valoracionRepository->visualizacionTotal($videoDTO->getId());
            $like = $valoracionRepository->favTotal($videoDTO->getId());
            $dislike = $valoracionRepository->dislikeTotal($videoDTO->getId());
            $visualizacionesDTO = [];

            $valoraciones = new ValoracionGlobalDTO();
            $valoraciones->setVisualizacion($visualizacion[0]['visualizacion']);
            $valoraciones->setFav($like[0]['fav']);
            $valoraciones->setDislike($dislike[0]['dislike']);
            $visualizacionesDTO[] = $valoraciones;

            $videoDTO->setValoracionGlobalDTO($visualizacionesDTO);

            $listaVideosDTO[]=$videoDTO;
        }

        return $this -> json($listaVideosDTO, Response::HTTP_OK);
    }

    //trae los videos ordenados por el número de reproducciones, de mayor a menor
    #[Route('/populares', name: 'videosPopulares', methods: ['GET'])]
    public function videosPopulares(ValoracionRepository $valoracionRepository,VideoRepository $videoRepository, Request $request) :JsonResponse
    {
        $populares = $videoRepository->buscarvideospopulares();

        foreach ($populares as &$subs) {
            $visualizacion = $valoracionRepository->visualizacionTotal($subs['id']);
            $like = $valoracionRepository->favTotal($subs['id']);
            $dislike = $valoracionRepository->dislikeTotal($subs['id']);

            $valoraciones = new ValoracionGlobalDTO();
            $valoraciones->setVisualizacion($visualizacion[0]['visualizacion']);
            $valoraciones->setFav($like[0]['fav']);
            $valoraciones->setDislike($dislike[0]['dislike']);

            // Agregar los valores de visualización, favoritos y disgustos al elemento actual de suscripciones
            $subs['visualizacion'] = $valoraciones->getVisualizacion();
            $subs['fav'] = $valoraciones->getFav();
            $subs['dislike'] = $valoraciones->getDislike();
        }

        dump($populares);
        return $this->json($populares, Response::HTTP_OK);
    }


    #[Route('/canalNombre/{canal}', name: 'getVideosByNombreCanal', methods: ["GET"])]
    public function getVideosByNombreCanal(VideoRepository $videoRepository, string $canal, ValoracionRepository $valoracionRepository):JsonResponse
    {
        $videos = $videoRepository -> getVideosByNombreCanal($canal);
        $listaVideosDTO=[];

        foreach ($videos as $video){
            $videoDTO = new videoDTO();
            $videoDTO ->setId($video['id']);
            $videoDTO ->setTitulo($video['titulo']);
            $videoDTO ->setDescripcion($video['descripcion']);
            $videoDTO ->setUrl($video['url']);
            $videoDTO ->setCanal($video['nombre_canal']);
            $videoDTO->setTematica($video['id_tematica']);

            $visualizacion = $valoracionRepository->visualizacionTotal($videoDTO->getId());
            $like = $valoracionRepository->favTotal($videoDTO->getId());
            $dislike = $valoracionRepository->dislikeTotal($videoDTO->getId());
            $visualizacionesDTO = [];

            $valoraciones = new ValoracionGlobalDTO();
            $valoraciones->setVisualizacion($visualizacion[0]['visualizacion']);
            $valoraciones->setFav($like[0]['fav']);
            $valoraciones->setDislike($dislike[0]['dislike']);
            $visualizacionesDTO[] = $valoraciones;

            $videoDTO->setValoracionGlobalDTO($visualizacionesDTO);

            $listaVideosDTO[]=$videoDTO;
        }

        return $this -> json($listaVideosDTO, Response::HTTP_OK);
    }

}
