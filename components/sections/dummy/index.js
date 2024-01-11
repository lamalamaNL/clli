import Image from '../../parts/image'
import { VideoHls } from '../../parts/video_hls'

export default class Dummy {
  constructor(section) {
    this.section = section
    if (this.section) {
      this.init()
    }
  }

  init = () => {
    this.createVideo()
    // this.createImage()
  }

  createVideo = () => {
    const video = this.section.querySelector('.js-video-hls')
    if (video) {
      this.video = new VideoHls(video)
    }
  }

  createImage = () => {
    const image = this.section.querySelector('.js-image')
    if (image) {
      this.image = new Image(image)
    }
  }

  destroy = () => {
    if (this.video && typeof this.video.destroy == 'function') {
      this.video.destroy('destroyed from dummy section')
    }

    if (this.image && typeof this.image.destroy == 'function') {
      this.image.destroy('destroyed from dummy section')
    }
  }
}
